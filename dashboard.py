import tkinter as tk
from tkinter import ttk
from PIL import Image, ImageTk
import requests
from io import BytesIO
import threading
import time
from datetime import datetime

API_URL = "http://localhost/qrgate/get_visitors.php"
UPDATE_INTERVAL = 0.5  # seconds

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

# Status colors
STATUS_COLORS = {
    "Valid": "#d1f2eb",  # light green
    "Expired": "#fadbd8",  # light red
    "Invalid": "#eaeded"  # light gray
}

# Status text colors
STATUS_TEXT_COLORS = {
    "Valid": "#27ae60",
    "Expired": "#e74c3c",
    "Invalid": "#7f8c8d"
}


class QRGateDashboard:
    def __init__(self):
        self.root = tk.Tk()
        self.root.title("QRGate Visitor Dashboard")
        self.root.configure(bg=COLORS["light"])
        self.root.state('zoomed')  # full-screen

        # Store row widgets for updates
        self.rows_widgets = []

        self.setup_ui()
        self.start_updates()

    def setup_ui(self):
        # Main container with padding
        main_container = tk.Frame(self.root, bg=COLORS["light"])
        main_container.pack(expand=True, fill="both", padx=20, pady=20)

        # Title header
        title_frame = tk.Frame(main_container, bg=COLORS["light"])
        title_frame.pack(fill="x", pady=(0, 20))

        title_label = tk.Label(
            title_frame,
            text="🎫 QRGate Visitor Dashboard",
            font=("Arial", 24, "bold"),
            bg=COLORS["light"],
            fg=COLORS["primary"]
        )
        title_label.pack()

        # Live status indicator
        status_frame = tk.Frame(title_frame, bg=COLORS["light"])
        status_frame.pack(pady=5)

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
            text="Live Updates",
            font=("Arial", 12),
            bg=COLORS["light"],
            fg=COLORS["dark"]
        )
        self.status_text.pack(side="left", padx=(5, 0))

        # Table container with border
        table_container = tk.Frame(main_container, bg=COLORS["white"], relief="solid", borderwidth=1)
        table_container.pack(expand=True, fill="both", pady=10)

        # Scrollable frame setup
        canvas = tk.Canvas(table_container, bg=COLORS["white"], highlightthickness=0)
        scrollbar = ttk.Scrollbar(table_container, orient="vertical", command=canvas.yview)

        self.scrollable_frame = tk.Frame(canvas, bg=COLORS["white"])

        self.scrollable_frame.bind(
            "<Configure>",
            lambda e: canvas.configure(scrollregion=canvas.bbox("all"))
        )

        canvas.create_window((0, 0), window=self.scrollable_frame, anchor="nw")
        canvas.configure(yscrollcommand=scrollbar.set)

        # Pack scrollbar and canvas
        scrollbar.pack(side="right", fill="y")
        canvas.pack(side="left", fill="both", expand=True)

        # Enable mouse wheel scrolling
        def on_mousewheel(event):
            canvas.yview_scroll(int(-1 * (event.delta / 120)), "units")

        canvas.bind_all("<MouseWheel>", on_mousewheel)

        self.canvas = canvas

        # Create table header
        self.create_header()

        # Footer with last update time
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

    def create_header(self):
        # Table header with enhanced styling
        columns = ["ID", "Visitor Name", "Email", "Purpose", "Host", "QR Code", "Expires At", "Status", "Last Scan"]
        column_widths = [50, 150, 180, 100, 120, 120, 140, 80, 140]

        header_frame = tk.Frame(self.scrollable_frame, bg=COLORS["primary"])
        header_frame.pack(fill="x", padx=10, pady=(10, 0))

        for i, (col, width) in enumerate(zip(columns, column_widths)):
            lbl = tk.Label(
                header_frame,
                text=col,
                bg=COLORS["primary"],
                fg=COLORS["white"],
                font=("Arial", 11, "bold"),
                width=width // 8,  # approximate character width
                relief="flat",
                pady=15
            )
            lbl.grid(row=0, column=i, sticky="nsew", padx=1)
            header_frame.grid_columnconfigure(i, weight=1 if i in [1, 2] else 0)

    def fetch_data(self):
        try:
            response = requests.get(API_URL, timeout=10)
            response.raise_for_status()
            data = response.json()

            # Handle both response formats
            if isinstance(data, dict) and 'data' in data:
                return data['data']
            elif isinstance(data, list):
                return data
            else:
                return []

        except Exception as e:
            print(f"Error fetching data: {e}")
            return []

    def update_dashboard(self):
        try:
            # Animate status dot
            current_color = self.status_dot.cget("fg")
            new_color = COLORS["warning"] if current_color == COLORS["success"] else COLORS["success"]
            self.status_dot.configure(fg=new_color)

            data = self.fetch_data()

            # Clear old rows
            for widgets in self.rows_widgets:
                for w in widgets:
                    if w.winfo_exists():
                        w.destroy()
            self.rows_widgets.clear()

            # Add new rows with enhanced styling
            for r, visitor in enumerate(data):
                self.create_visitor_row(r + 1, visitor)

            # Update last updated time
            current_time = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            self.last_update_label.configure(text=f"Last updated: {current_time}")

            # Reset status dot
            self.status_dot.configure(fg=COLORS["success"])

        except Exception as e:
            print(f"Error updating dashboard: {e}")
            self.status_dot.configure(fg=COLORS["danger"])

    def create_visitor_row(self, row_num, visitor):
        # Determine status and colors
        status = visitor.get('last_status', 'Invalid')
        if status not in STATUS_COLORS:
            status = "Invalid"

        bg_color = STATUS_COLORS.get(status, "#eaeded")
        status_text_color = STATUS_TEXT_COLORS.get(status, "#7f8c8d")

        # Alternate row colors for better readability
        if row_num % 2 == 0:
            bg_color = self.lighten_color(bg_color)

        # Row frame
        row_frame = tk.Frame(self.scrollable_frame, bg=bg_color)
        row_frame.pack(fill="x", padx=10, pady=1)

        row_widgets = []

        # Column data
        columns_data = [
            str(visitor.get('visitor_id', '')),
            visitor.get('full_name', ''),
            visitor.get('email', ''),
            visitor.get('purpose', ''),
            visitor.get('host', ''),
            '',  # QR code placeholder
            self.format_datetime(visitor.get('expiry_at', '')),
            status,
            self.format_datetime(visitor.get('last_scan', '')) or "Never"
        ]

        column_widths = [50, 150, 180, 100, 120, 120, 140, 80, 140]

        for i, (data, width) in enumerate(zip(columns_data, column_widths)):
            if i == 5:  # QR code column
                self.create_qr_widget(row_frame, i, visitor.get('qr_code', ''), bg_color, row_widgets)
            elif i == 7:  # Status column
                lbl = tk.Label(
                    row_frame,
                    text=data,
                    bg=bg_color,
                    fg=status_text_color,
                    font=("Arial", 10, "bold"),
                    width=width // 8,
                    pady=10
                )
                lbl.grid(row=0, column=i, sticky="nsew", padx=1)
                row_widgets.append(lbl)
            else:
                lbl = tk.Label(
                    row_frame,
                    text=data,
                    bg=bg_color,
                    fg=COLORS["dark"],
                    font=("Arial", 10),
                    width=width // 8,
                    pady=10,
                    wraplength=width - 10
                )
                lbl.grid(row=0, column=i, sticky="nsew", padx=1)
                row_widgets.append(lbl)

        # Configure grid weights
        for i in range(len(columns_data)):
            row_frame.grid_columnconfigure(i, weight=1 if i in [1, 2] else 0)

        self.rows_widgets.append(row_widgets)

    def create_qr_widget(self, parent, column, qr_code, bg_color, row_widgets):
        qr_frame = tk.Frame(parent, bg=bg_color)
        qr_frame.grid(row=0, column=column, sticky="nsew", padx=1, pady=5)

        try:
            if qr_code:
                qr_url = f"https://api.qrserver.com/v1/create-qr-code/?size=80x80&data={qr_code}"
                response = requests.get(qr_url, timeout=5)

                if response.status_code == 200:
                    img = Image.open(BytesIO(response.content))
                    img = img.resize((60, 60), Image.Resampling.LANCZOS)
                    photo = ImageTk.PhotoImage(img)

                    qr_label = tk.Label(qr_frame, image=photo, bg=bg_color)
                    qr_label.image = photo  # Keep reference
                    qr_label.pack(pady=2)

                    # QR code text below image
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

        # Fallback if QR code fails to load
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
        """Lighten a hex color slightly for alternating rows"""
        # Simple color lightening - convert hex to RGB, lighten, convert back
        color = color.lstrip('#')
        rgb = tuple(int(color[i:i + 2], 16) for i in (0, 2, 4))
        lightened = tuple(min(255, c + 10) for c in rgb)
        return f"#{lightened[0]:02x}{lightened[1]:02x}{lightened[2]:02x}"

    def format_datetime(self, dt_string):
        """Format datetime string for display"""
        if not dt_string or dt_string == "None":
            return ""
        try:
            # Parse the datetime string and format it nicely
            dt = datetime.strptime(dt_string, "%Y-%m-%d %H:%M:%S")
            return dt.strftime("%m/%d/%Y %H:%M")
        except:
            return dt_string

    def periodic_update(self):
        while True:
            try:
                self.root.after(0, self.update_dashboard)
                time.sleep(UPDATE_INTERVAL)
            except Exception as e:
                print(f"Error in periodic update: {e}")
                time.sleep(UPDATE_INTERVAL)

    def start_updates(self):
        # Start update thread
        update_thread = threading.Thread(target=self.periodic_update, daemon=True)
        update_thread.start()

        # Initial load
        self.root.after(1000, self.update_dashboard)

    def run(self):
        self.root.mainloop()


if __name__ == "__main__":
    app = QRGateDashboard()
    app.run()