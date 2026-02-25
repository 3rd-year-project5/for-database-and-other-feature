from playwright.sync_api import sync_playwright
print("Starting Playwright test...")
with sync_playwright() as p:
    browser = p.chromium.launch(headless=True)
    page = browser.new_page()
    page.goto("https://qrgate-system.infinityfreeapp.com/qrgate/get_visitors.php", wait_until="networkidle", timeout=30000)
    body = page.content()
    print("Loaded length:", len(body))
    print(body[:1000])
    browser.close()