import tkinter as tk
from tkinter import ttk, messagebox, filedialog
from PIL import Image, ImageTk
import requests
from io import BytesIO
import threading
import time
from datetime import datetime, date
import csv
import ssl
import urllib3

# Disable SSL warnings (for testing - remove in production)
urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

API_URL = "http://localhost/qrgate/get_visitors.php"
UPDATE_INTERVAL = 15  #15 seconds

# Enhanced color scheme
COLORS = {
    "primary": "#2c3e50",
    "success": "#27ae60",
    "danger": "#e74c3c",
    "warning": "#f39c12",
    "light": "#ecf0f1",
    "dark": "#34495e",
    "white": "#ffffff"
}

# Column definitions (widths in pixels) - keep in sync for header and rows
COLUMNS = ["ID", "Visitor Name", "Email", "Purpose", "Host", "QR Code", "Expires At", "Status", "Last Scan", "Duration", "Created At"]
COLUMN_WIDTHS = [50, 150, 180, 120, 120, 80, 140, 80, 140, 100, 140]

# Status colors - Updated to match Arduino LED colors
STATUS_COLORS = {
    "Valid": "#d1f2eb",
    "Expired": "#fff3cd",
    "Invalid": "#fadbd8",
    "Inside": "#e8f4fd"
}

# Status text colors
STATUS_TEXT_COLORS = {
    "Valid": "#27ae60",
    "Expired": "#856404",
    "Invalid": "#e74c3c",
    "Inside": "#0c5460"
}


class QRGateDashboard:
    def __init__(self):
        self.root = tk.Tk()
        self.root.title("QRGate Visitor Dashboard")
        self.root.configure(bg=COLORS["light"])
        self.root.state('zoomed')

        self.rows_widgets = []
        self.current_data = []
        self.filtered_data = []
        self.search_var = tk.StringVar()
        self.filter_status = "All"

        # Performance optimization variables
        self.is_updating = False
        self.last_qr_cache = {}
        self.qr_images_cache = {}
        self.last_data_hash = None
        self.auto_refresh = True
        self.connection_error_count = 0  # Track connection errors

        self.setup_ui()
        self.start_updates()

    def setup_ui(self):
        # Main container
        main_container = tk.Frame(self.root, bg=COLORS["light"])
        main_container.pack(expand=True, fill="both", padx=20, pady=20)

        # Title header
        title_frame = tk.Frame(main_container, bg=COLORS["light"])
        title_frame.pack(fill="x", pady=(0, 10))

        title_label = tk.Label(
            title_frame,
            text="QRGate Visitor Dashboard",
            font=("Arial", 24, "bold"),
            bg=COLORS["light"],
            fg=COLORS["primary"]
        )
        title_label.pack()

        # Connection info label
        self.connection_info = tk.Label(
            title_frame,
            text=f"Connected to: {API_URL}",
            font=("Arial", 9),
            bg=COLORS["light"],
            fg=COLORS["dark"]
        )
        self.connection_info.pack()

        # Statistics Panel
        self.create_stats_panel(main_container)

        # Control Panel (Search, Filter, Export)
        self.create_control_panel(main_container)

        # Status indicator
        status_frame = tk.Frame(main_container, bg=COLORS["light"])
        status_frame.pack(fill="x", pady=5)

        self.status_dot = tk.Label(
            status_frame,
            text="●",
            font=("Arial", 16),
            fg=COLORS["success"],
            bg=COLORS["light"]
        )
        self.status_dot.pack(side="left")

        self.status_text = tk.Label(
            status_frame,
            text="Connecting...",
            font=("Arial", 12),
            bg=COLORS["light"],
            fg=COLORS["dark"]
        )
        self.status_text.pack(side="left", padx=(5, 0))

        # Table container
        table_container = tk.Frame(main_container, bg=COLORS["white"], relief="solid", borderwidth=1)
        table_container.pack(expand=True, fill="both", pady=10)

        # Scrollable frame
        canvas = tk.Canvas(table_container, bg=COLORS["white"], highlightthickness=0)
        scrollbar = ttk.Scrollbar(table_container, orient="vertical", command=canvas.yview)

        self.scrollable_frame = tk.Frame(canvas, bg=COLORS["white"])
        self.scrollable_frame.bind(
            "<Configure>",
            lambda e: canvas.configure(scrollregion=canvas.bbox("all"))
        )

        canvas.create_window((0, 0), window=self.scrollable_frame, anchor="nw")
        canvas.configure(yscrollcommand=scrollbar.set)

        scrollbar.pack(side="right", fill="y")
        canvas.pack(side="left", fill="both", expand=True)

        def on_mousewheel(event):
            canvas.yview_scroll(int(-1 * (event.delta / 120)), "units")

        canvas.bind_all("<MouseWheel>", on_mousewheel)
        self.canvas = canvas

        # Create table header
        self.create_header()

        self.data_frame = tk.Frame(self.scrollable_frame, bg=COLORS["white"])
        self.data_frame.pack(fill="x")

        # Footer
        footer_frame = tk.Frame(main_container, bg=COLORS["light"])
        footer_frame.pack(fill="x", pady=(10, 0))

        self.last_update_label = tk.Label(
            footer_frame,
            text="Last updated: Never",
            font=("Arial", 10),
            bg=COLORS["light"],
            fg=COLORS["dark"]
        )
        self.last_update_label.pack()

    def create_stats_panel(self, parent):
        stats_frame = tk.Frame(parent, bg=COLORS["light"])
        stats_frame.pack(fill="x", pady=10)

        self.stat_total = self.create_stat_card(stats_frame, "Total Visitors", "0", COLORS["primary"])
        self.stat_valid = self.create_stat_card(stats_frame, "Valid Access", "0", COLORS["success"])
        self.stat_expired = self.create_stat_card(stats_frame, "Expired", "0", COLORS["warning"])
        self.stat_pending = self.create_stat_card(stats_frame, "Inside", "0", COLORS["dark"])

    def create_stat_card(self, parent, label, value, color):
        card = tk.Frame(parent, bg=color, relief="raised", borderwidth=2)
        card.pack(side="left", padx=10, fill="both", expand=True)

        tk.Label(card, text=label, font=("Arial", 12), bg=color, fg=COLORS["white"]).pack(pady=(10, 5))
        value_label = tk.Label(card, text=value, font=("Arial", 20, "bold"), bg=color, fg=COLORS["white"])
        value_label.pack(pady=(5, 10))

        return value_label

    def create_control_panel(self, parent):
        control_frame = tk.Frame(parent, bg=COLORS["light"])
        control_frame.pack(fill="x", pady=5)

        # Search
        tk.Label(control_frame, text="Search:", bg=COLORS["light"], font=("Arial", 10)).pack(side="left", padx=5)

        search_entry = tk.Entry(control_frame, textvariable=self.search_var, width=30, font=("Arial", 10))
        search_entry.pack(side="left", padx=5)
        self.search_var.trace("w", lambda *args: self.apply_filters())

        # Filter buttons
        tk.Button(control_frame, text="All", command=lambda: self.set_filter("All"),
                  bg=COLORS["primary"], fg="white", padx=10).pack(side="left", padx=2)
        tk.Button(control_frame, text="Valid", command=lambda: self.set_filter("Valid"),
                  bg=COLORS["success"], fg="white", padx=10).pack(side="left", padx=2)
        tk.Button(control_frame, text="Expired", command=lambda: self.set_filter("Expired"),
                  bg=COLORS["warning"], fg="white", padx=10).pack(side="left", padx=2)
        tk.Button(control_frame, text="Inside", command=lambda: self.set_filter("Inside"),
                  bg=COLORS["dark"], fg="white", padx=10).pack(side="left", padx=2)
        tk.Button(control_frame, text="Exited", command=lambda: self.set_filter("Exited"),
                  bg=COLORS["danger"], fg="white", padx=10).pack(side="left", padx=2)
        
        # Refresh and Export controls
        self.auto_refresh_btn = tk.Button(
            control_frame,
            text="⏸️ Auto Refresh",
            command=self.toggle_auto_refresh,
            bg=COLORS["warning"],
            fg="white",
            padx=15
        )
        self.auto_refresh_btn.pack(side="right", padx=5)

        tk.Button(control_frame, text="🔄 Refresh Now", command=self.manual_refresh,
                  bg=COLORS["primary"], fg="white", padx=15).pack(side="right", padx=5)
        tk.Button(control_frame, text="📊 Export CSV", command=self.export_to_csv,
                  bg=COLORS["success"], fg="white", padx=15).pack(side="right", padx=5)

    def toggle_auto_refresh(self):
        self.auto_refresh = not self.auto_refresh
        if self.auto_refresh:
            self.auto_refresh_btn.config(text="⏸️ Auto Refresh", bg=COLORS["warning"])
            self.status_text.config(text="Live Updates")
        else:
            self.auto_refresh_btn.config(text="▶️ Auto Refresh", bg=COLORS["success"])
            self.status_text.config(text="Updates Paused")

    def manual_refresh(self):
        """Manual refresh triggered by user"""
        if not self.is_updating:
            self.update_dashboard()

    def set_filter(self, status):
        self.filter_status = status
        self.apply_filters()

    def apply_filters(self):
        search_text = self.search_var.get().lower()

        self.filtered_data = []
        for visitor in self.current_data:
            # Apply status filter
            if self.filter_status != "All":
                visitor_status = self.get_visitor_status(visitor)
                if visitor_status != self.filter_status:
                    continue

            # Apply search filter
            if search_text:
                searchable_text = f"{visitor.get('full_name', '')} {visitor.get('email', '')} {visitor.get('purpose', '')} {visitor.get('host', '')}".lower()
                if search_text not in searchable_text:
                    continue

            self.filtered_data.append(visitor)

        self.display_data(self.filtered_data)

    def get_visitor_status(self, visitor):
        """Determine visitor status based on last_status and expiry"""
        last_status_raw = visitor.get('last_status')
        last_status = str(last_status_raw or "").strip().lower()

        expiry_str = visitor.get('expiry_at', '')
        last_scan = visitor.get('last_scan')

        # Check if expired
        try:
            expiry = datetime.strptime(expiry_str, '%Y-%m-%d %H:%M:%S')
            now = datetime.now()
            is_expired = expiry < now
        except:
            is_expired = False

        exit_statuses = ['exited', 'exit', 'left', 'out', 'exited_by']

        # PRIORITY 1: Check last_status first (most reliable)
        if last_status == "invalid":
            return "Invalid"
        
        if last_status in exit_statuses:
            return "Exited"
        
        if last_status == "inside":
            # Only show Inside if not expired and has a scan time
            if is_expired:
                return "Expired"
            if last_scan and last_scan not in ["None", "", None]:
                return "Inside"
        
        if last_status == "expired":
            return "Expired"

        # PRIORITY 2: Check expiry date
        if is_expired:
            return "Expired"

        # PRIORITY 3: Check if they have scanned in (but status is not explicitly set)
        if last_scan and last_scan not in ["None", "", None] and last_status not in ["invalid", "expired"]:
            return "Inside"

        # Default: Valid (not scanned yet, not expired)
        return "Valid"


    def export_to_csv(self):
        if not self.current_data:
            messagebox.showwarning("No Data", "No data to export!")
            return

        # Ask user for filename
        default_filename = f"visitors_{datetime.now().strftime('%Y%m%d_%H%M%S')}.csv"
        filename = filedialog.asksaveasfilename(
            title="Export CSV File",
            defaultextension=".csv",
            filetypes=[("CSV files", "*.csv"), ("All files", "*.*")],
            initialfile=default_filename
        )

        if not filename:
            return

        try:
            with open(filename, 'w', newline='', encoding='utf-8') as file:
                writer = csv.writer(file)
                # include duration column
                writer.writerow(
                    ["ID", "Name", "Email", "Phone", "Purpose", "Host", "QR Code", "Status", "Expires At", "Last Scan", "Duration",
                     "Created At"])

                for visitor in self.current_data:
                    duration = self.format_duration(
                        last_scan=visitor.get('entry_scan') or visitor.get('last_scan'),
                        exit_ts=visitor.get('exit_time'),
                        last_status=self.get_visitor_status(visitor)
                    )
                    writer.writerow([
                        visitor.get('visitor_id', ''),
                        visitor.get('full_name', ''),
                        visitor.get('email', ''),
                        visitor.get('phone', ''),
                        visitor.get('purpose', ''),
                        visitor.get('host', ''),
                        visitor.get('qr_code', ''),
                        self.get_visitor_status(visitor),
                        visitor.get('expiry_at', ''),
                        visitor.get('last_scan', ''),
                        duration,
                        visitor.get('created_at', '')
                    ])

            messagebox.showinfo("Export Successful", f"Data exported to:\n{filename}")
        except Exception as e:
            messagebox.showerror("Export Failed", f"Error: {str(e)}")

    def create_header(self):
        columns = COLUMNS
        column_widths = COLUMN_WIDTHS

        header_frame = tk.Frame(self.scrollable_frame, bg=COLORS["primary"])
        header_frame.pack(fill="x", padx=10, pady=(10, 0))

        for i, (col, width) in enumerate(zip(columns, column_widths)):
            lbl = tk.Label(
                header_frame,
                text=col,
                bg=COLORS["primary"],
                fg=COLORS["white"],
                font=("Arial", 11, "bold"),
                width=max(8, width // 10),
                relief="flat",
                pady=15
            )
            lbl.grid(row=0, column=i, sticky="w", padx=1)
            header_frame.grid_columnconfigure(i, minsize=width, weight=0)
    def display_data(self, data):
        # Clear old rows
        for child in self.data_frame.winfo_children():
            child.destroy()
        self.rows_widgets.clear()

        if not data:
            no_data_label = tk.Label(
                self.data_frame,
                text="No visitors found. Check your connection or try refreshing.",
                font=("Arial", 14),
                bg=COLORS["white"],
                fg=COLORS["dark"],
                pady=50
            )
            no_data_label.pack()
            return

        # Batch create all rows at once (defer image loading)
        for r, visitor in enumerate(data):
            self.create_visitor_row(r + 1, visitor)
        
        # Force UI update after all rows created
        self.data_frame.update_idletasks()

    def fetch_data(self):
        try:
            # Add detailed error logging
            print(f"Attempting to fetch from: {API_URL}")
            headers = {
                "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36",
                "Accept": "application/json, text/javascript, */*; q=0.01",
                "Referer": API_URL
            }
            response = requests.get(API_URL, headers=headers, timeout=15, verify=False)
            print(f"Response status code: {response.status_code}")
            print(f"Response headers: {response.headers}")
            response.raise_for_status()

            data = response.json()
            
            print(f"Response data type: {type(data)}")
            print(f"Response data keys: {data.keys() if isinstance(data, dict) else 'N/A'}")

            # Reset error count on success
            self.connection_error_count = 0

            if isinstance(data, dict) and 'data' in data:
                print(f"Found {len(data['data'])} visitors")
                return data['data']
            elif isinstance(data, list):
                print(f"Found {len(data)} visitors (list format)")
                return data
            else:
                print(f"Unexpected data format: {data}")
                return []

        except requests.exceptions.SSLError as e:
            self.connection_error_count += 1
            print(f"SSL Error: {e}")
            self.show_connection_error(f"SSL Certificate Error. Try accessing the website in your browser first.")
            return []
        except requests.exceptions.ConnectionError as e:
            self.connection_error_count += 1
            print(f"Connection Error: {e}")
            self.show_connection_error(f"Cannot connect to server. Check your internet connection.")
            return []
        except requests.exceptions.Timeout as e:
            self.connection_error_count += 1
            print(f"Timeout Error: {e}")
            self.show_connection_error(f"Server took too long to respond.")
            return []
        except requests.exceptions.HTTPError as e:
            self.connection_error_count += 1
            print(f"HTTP Error: {e}")
            self.show_connection_error(f"Server error: {e}")
            return []
        except ValueError as e:
            self.connection_error_count += 1
            print(f"JSON Decode Error: {e}")
            self.show_connection_error(f"Invalid response from server.")
            return []
        except Exception as e:
            self.connection_error_count += 1
            print(f"Unexpected error fetching data: {e}")
            self.show_connection_error(f"Error: {str(e)}")
            return []

    def show_connection_error(self, message):
        """Update UI to show connection error"""
        self.root.after(0, lambda: self.status_text.config(
            text=f"Connection Error ({self.connection_error_count})",
            fg=COLORS["danger"]
        ))
        
        # Show error dialog only on first few errors
        if self.connection_error_count <= 3:
            self.root.after(0, lambda: messagebox.showerror(
                "Connection Error",
                f"{message}\n\nURL: {API_URL}\n\nMake sure:\n1. The website is accessible\n2. get_visitors.php exists\n3. Database is configured correctly"
            ))

    def calculate_data_hash(self, data):
        """Create a simple hash to detect data changes"""
        import hashlib
        data_str = str([(v.get('visitor_id'), v.get('last_scan')) for v in data])
        return hashlib.md5(data_str.encode()).hexdigest()

    def update_dashboard(self):
        if self.is_updating:
            return

        self.is_updating = True
        try:
            # Visual feedback
            current_color = self.status_dot.cget("fg")
            new_color = COLORS["warning"] if current_color == COLORS["success"] else COLORS["success"]
            self.status_dot.configure(fg=new_color)

            # Fetch new data
            new_data = self.fetch_data()

            # Check if we got data
            if not new_data and self.connection_error_count > 0:
                self.status_dot.configure(fg=COLORS["danger"])
                self.status_text.configure(text="Connection Failed", fg=COLORS["danger"])
                return

            # Check if data actually changed
            new_hash = self.calculate_data_hash(new_data)
            data_changed = new_hash != self.last_data_hash

            if data_changed or not self.current_data:
                # New/changed data - update everything
                self.last_data_hash = new_hash
                self.current_data = new_data
                self.update_statistics()

            # ALWAYS redraw so duration ticks live for Inside visitors
            self.apply_filters()

            current_time = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            self.last_update_label.configure(text=f"Last updated: {current_time}")

            self.status_dot.configure(fg=COLORS["success"])
            self.status_text.configure(text="Live Updates", fg=COLORS["dark"])

        except Exception as e:
            import traceback
            print("===== UPDATE ERROR START =====")
            traceback.print_exc()
            print("===== UPDATE ERROR END =====")
            self.status_dot.configure(fg=COLORS["danger"])
            self.status_text.configure(text="Update Error", fg=COLORS["danger"])

        finally:
            self.is_updating = False

    def update_statistics(self):
        total = len(self.current_data)
        valid = len([v for v in self.current_data if self.get_visitor_status(v) == "Valid"])
        expired = len([v for v in self.current_data if self.get_visitor_status(v) == "Expired"])
        pending = len([v for v in self.current_data if self.get_visitor_status(v) == "Inside"])

        self.stat_total.configure(text=str(total))
        self.stat_valid.configure(text=str(valid))
        self.stat_expired.configure(text=str(expired))
        self.stat_pending.configure(text=str(pending))

    def create_visitor_row(self, row_num, visitor):
        status = self.get_visitor_status(visitor)

        bg_color = STATUS_COLORS.get(status, "#eaeded")
        status_text_color = STATUS_TEXT_COLORS.get(status, "#7f8c8d")

        if row_num % 2 == 0:
            bg_color = self.lighten_color(bg_color)

        row_frame = tk.Frame(self.data_frame, bg=bg_color)
        row_frame.pack(fill="x", padx=10, pady=1)

        row_widgets = []

        # Duration = time inside only. Use computed status so Expired always shows '-'
        duration_str = self.format_duration(
            last_scan=visitor.get('entry_scan') or visitor.get('last_scan'),
            exit_ts=visitor.get('exit_time'),
            last_status=self.get_visitor_status(visitor)
        )

        columns_data = [
            str(visitor.get('visitor_id', '')),
            visitor.get('full_name', ''),
            visitor.get('email', ''),
            visitor.get('purpose', ''),
            visitor.get('host', ''),
            '',
            self.format_datetime(visitor.get('expiry_at', '')),
            status,
            self.format_datetime(visitor.get('last_scan', '')) or "Never",
            duration_str,
            self.format_datetime(visitor.get('created_at', ''))
        ]

        column_widths = COLUMN_WIDTHS

        for i, (data, width) in enumerate(zip(columns_data, column_widths)):
            if i == 5:   # QR code cell
                self.create_qr_widget(row_frame, i, visitor.get('qr_code', ''), bg_color, row_widgets)
            elif i == 7:
                lbl = tk.Label(
                    row_frame,
                    text=data,
                    bg=bg_color,
                    fg=status_text_color,
                    font=("Arial", 10, "bold"),
                    width=max(6, width // 10),
                    pady=10,
                    anchor="w",
                    justify="left"
                )
                lbl.grid(row=0, column=i, sticky="w", padx=1)
                row_widgets.append(lbl)
            else:
                lbl = tk.Label(
                    row_frame,
                    text=data,
                    bg=bg_color,
                    fg=COLORS["dark"],
                    font=("Arial", 10),
                    width=max(6, width // 10),
                    pady=10,
                    wraplength=max(50, width - 20),
                    anchor="w",
                    justify="left"
                )
                lbl.grid(row=0, column=i, sticky="w", padx=1)
                row_widgets.append(lbl)

        for i, w in enumerate(column_widths):
            row_frame.grid_columnconfigure(i, minsize=w, weight=0)

        self.rows_widgets.append(row_widgets)

    def create_qr_widget(self, parent, column, qr_code, bg_color, row_widgets):
        qr_frame = tk.Frame(parent, bg=bg_color)
        qr_frame.grid(row=0, column=column, sticky="w", padx=1, pady=5)

        # Use cached QR code if available
        if qr_code in self.qr_images_cache:
            cached_img = self.qr_images_cache[qr_code]
            qr_label = tk.Label(qr_frame, image=cached_img, bg=bg_color)
            qr_label.image = cached_img
            qr_label.pack(pady=2)

            code_label = tk.Label(
                qr_frame,
                text=qr_code[:8] + "...",
                font=("Courier", 8),
                bg=bg_color,
                fg=COLORS["dark"]
            )
            code_label.pack()
            row_widgets.extend([qr_label, code_label])
            return

        try:
            if qr_code:
                qr_url = f"https://api.qrserver.com/v1/create-qr-code/?size=80x80&data={qr_code}"
                response = requests.get(qr_url, timeout=5)

                if response.status_code == 200:
                    img = Image.open(BytesIO(response.content))
                    # Reduce QR image size to avoid misalignment
                    resample = getattr(Image, "Resampling", None)
                    resample_filter = Image.Resampling.LANCZOS if resample else Image.ANTIALIAS
                    img = img.resize((36, 36), resample_filter)
                    photo = ImageTk.PhotoImage(img)

                    # Cache the image
                    self.qr_images_cache[qr_code] = photo

                    qr_label = tk.Label(qr_frame, image=photo, bg=bg_color)
                    qr_label.image = photo
                    qr_label.pack(pady=2)

                    code_label = tk.Label(
                        qr_frame,
                        text=qr_code[:8] + "...",
                        font=("Courier", 8),
                        bg=bg_color,
                        fg=COLORS["dark"]
                    )
                    code_label.pack()

                    row_widgets.extend([qr_label, code_label])
                    return
        except Exception as e:
            print(f"Error loading QR code: {e}")

        fallback_label = tk.Label(
            qr_frame,
            text="QR\nCode",
            bg=bg_color,
            fg=COLORS["dark"],
            font=("Arial", 8),
            justify="center"
        )
        fallback_label.pack(pady=15)
        row_widgets.append(fallback_label)

    def lighten_color(self, color):
        color = color.lstrip('#')
        rgb = tuple(int(color[i:i + 2], 16) for i in (0, 2, 4))
        lightened = tuple(min(255, c + 10) for c in rgb)
        return f"#{lightened[0]:02x}{lightened[1]:02x}{lightened[2]:02x}"

    def format_datetime(self, dt_string):
        if not dt_string or dt_string == "None":
            return ""
        try:
            dt = datetime.strptime(dt_string, "%Y-%m-%d %H:%M:%S")
            return dt.strftime("%m/%d/%Y %H:%M")
        except:
            return dt_string

    def format_duration(self, last_scan=None, exit_ts=None, last_status=None):
        """Duration = time spent INSIDE only.
        - Valid/Expired/Invalid -> "-"  (never entered)
        - Inside  -> entry_scan to now  (live ticking)
        - Exited  -> entry_scan to exit_time (fixed)
        """
        try:
            status = str(last_status or "").lower()
            if status in ("valid", "expired", "invalid", ""):
                return "-"
            if not last_scan or last_scan in ("None", None):
                return "-"
            fmt = "%Y-%m-%d %H:%M:%S"
            entry = datetime.strptime(last_scan, fmt)
            if status in ("exited", "left", "out", "exited_by"):
                if not exit_ts or exit_ts in ("None", "", None):
                    return "-"
                end = datetime.strptime(exit_ts, fmt)
            elif status == "inside":
                end = datetime.now()
            else:
                return "-"
            delta = end - entry
            if delta.total_seconds() < 0:
                return "-"
            days  = delta.days
            hours = delta.seconds // 3600
            mins  = (delta.seconds % 3600) // 60
            if days > 0:
                return f"{days}d {hours}h {mins}m"
            if hours > 0:
                return f"{hours}h {mins}m"
            if mins > 0:
                return f"{mins}m"
            return "just now"
        except Exception:
            return "-"

    def periodic_update(self):
        while True:
            try:
                if self.auto_refresh and not self.is_updating:
                    self.root.after(0, self.update_dashboard)
                time.sleep(UPDATE_INTERVAL)
            except Exception as e:
                print(f"Error in periodic update: {e}")
                time.sleep(UPDATE_INTERVAL)

    def start_updates(self):
        update_thread = threading.Thread(target=self.periodic_update, daemon=True)
        update_thread.start()
        self.root.after(1000, self.update_dashboard)

    def run(self):
        self.root.mainloop()


if __name__ == "__main__":
    print("=== QRGate Dashboard Starting ===")
    print(f"API URL: {API_URL}")
    print("Attempting first connection...")
    
    app = QRGateDashboard()
    app.run()