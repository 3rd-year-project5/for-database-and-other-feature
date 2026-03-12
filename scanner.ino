// ====================================================
// ESP32-CAM QR GATE - WiFiManager + Serial Reset
// ====================================================
// To reset WiFi: type RESET in Serial Monitor and press Enter
// ====================================================
#include "soc/rtc_cntl_reg.h"
#include <WiFi.h>
#include <HTTPClient.h>
#include <WiFiClientSecure.h>
#include <ArduinoJson.h>
#include <Wire.h>
#include <LiquidCrystal_I2C.h>
#include "esp_camera.h"
#include <ESP32QRCodeReader.h>
#include <WiFiManager.h>
#include <map>
#include <freertos/FreeRTOS.h>
#include <freertos/semphr.h>

// ==================== HARDWARE PINS ====================
#define SDA_PIN     14   // LCD I2C SDA
#define SCL_PIN     15   // LCD I2C SCL
#define GREEN_LED   12   // Green LED
#define RED_LED     3    // Red LED
#define YELLOW_LED  4    // Yellow LED
#define BUZZER      2    // Buzzer

// ==================== SERVER ====================
const char* serverURL = "https://qrgate-production.up.railway.app/check.php";
const char* exitURL   = "https://qrgate-production.up.railway.app/log_exit.php";

// ==================== LCD & QR READER ====================
LiquidCrystal_I2C lcd(0x27, 16, 2);
ESP32QRCodeReader reader(CAMERA_MODEL_AI_THINKER);

// ==================== STATE ====================
unsigned long lastDisplayTime     = 0;
unsigned long gateOpenTime        = 0;
const unsigned long DISPLAY_DURATION   = 3000;
const unsigned long GATE_OPEN_DURATION = 5000;

bool   isGateOpen = false;
String lastQRCode = "";

struct VisitorEntry {
  String qrCode;
  String visitorName;
  bool   isInside;
};
std::map<String, VisitorEntry> activeVisitors;

// ==================== SYNCHRONIZATION ====================
SemaphoreHandle_t xMutex;
volatile bool processingQR = false;

// ==================== FORWARD DECLARATIONS ====================
void testHardware();
void displayWaitingMessage();
void displayReadyType(bool exitMode);
void processQRCode(String qrCode);
void handleValidQR(DynamicJsonDocument& doc, String qrCode);
void handleExpiredQR();
void handleInvalidQR();
void handleAlreadyExited(DynamicJsonDocument& doc);
void openGate();
void closeGate();
bool inside(String qr);
void startWiFiManager();
void resetWiFi();

// ==================== QR TASK (CORE 1) ====================
void onQrCodeTask(void *pvParameters) {
  QRCodeData qrData;
  while (true) {
    if (reader.receiveQrCode(&qrData, 100)) {
      if (qrData.valid) {
        String qr = String((const char*)qrData.payload);
        Serial.print("Camera QR: "); Serial.println(qr);

        bool isInside = inside(qr);

        bool shouldProcess = false;
        xSemaphoreTake(xMutex, portMAX_DELAY);
        bool gateClosed    = !isGateOpen;
        bool notProcessing = !processingQR;
        bool qrDiff        = (qr != lastQRCode);
        if (gateClosed && notProcessing && (qrDiff || isInside)) {
          processingQR  = true;
          shouldProcess = true;
        }
        xSemaphoreGive(xMutex);

        if (shouldProcess) {
          displayReadyType(isInside);
          delay(400);
          processQRCode(qr);
          xSemaphoreTake(xMutex, portMAX_DELAY);
          lastQRCode   = qr;
          processingQR = false;
          xSemaphoreGive(xMutex);
        }
      }
    }
    vTaskDelay(100 / portTICK_PERIOD_MS);
  }
}

// ==================== GATE FUNCTIONS ====================
void openGate() {
  xSemaphoreTake(xMutex, portMAX_DELAY);
  isGateOpen   = true;
  gateOpenTime = millis();
  digitalWrite(GREEN_LED,  HIGH);
  digitalWrite(RED_LED,    LOW);
  digitalWrite(YELLOW_LED, LOW);
  xSemaphoreGive(xMutex);
}

void closeGate() {
  xSemaphoreTake(xMutex, portMAX_DELAY);
  isGateOpen = false;
  lastQRCode = "";
  digitalWrite(GREEN_LED,  LOW);
  digitalWrite(RED_LED,    LOW);
  digitalWrite(YELLOW_LED, LOW);
  xSemaphoreGive(xMutex);
}

// ==================== ENTRY / EXIT ====================
bool inside(String qr) {
  return activeVisitors.count(qr) && activeVisitors[qr].isInside;
}

void recordEntry(String qr, String name) {
  xSemaphoreTake(xMutex, portMAX_DELAY);
  activeVisitors[qr] = {qr, name, true};
  xSemaphoreGive(xMutex);
}

void recordExit(String qr) {
  xSemaphoreTake(xMutex, portMAX_DELAY);
  if (activeVisitors.count(qr)) {
    activeVisitors[qr].isInside = false;
  }
  xSemaphoreGive(xMutex);

  WiFiClientSecure client;
  client.setInsecure();
  HTTPClient http;
  http.begin(client, exitURL);
  http.addHeader("Content-Type", "application/x-www-form-urlencoded");
  http.POST("qr_code=" + qr);
  http.end();
}

void handleExit(String qr) {
  digitalWrite(YELLOW_LED, LOW);
  digitalWrite(GREEN_LED,  HIGH);
  tone(BUZZER, 1800, 100); delay(150);
  tone(BUZZER, 1200, 150);

  String name;
  xSemaphoreTake(xMutex, portMAX_DELAY);
  name = activeVisitors.count(qr) ? activeVisitors[qr].visitorName : "Unknown";
  xSemaphoreGive(xMutex);

  lcd.clear();
  lcd.print("EXIT - Goodbye!");
  lcd.setCursor(0, 1);
  lcd.print(name.substring(0, 16));

  recordExit(qr);
  delay(1000);
  openGate();
  lastDisplayTime = millis();
}

// ==================== WIFI RESET ====================
void resetWiFi() {
  Serial.println("Resetting WiFi credentials...");
  lcd.clear();
  lcd.print("Resetting WiFi");
  lcd.setCursor(0, 1);
  lcd.print("Please wait...");

  digitalWrite(RED_LED, HIGH);
  tone(BUZZER, 1000, 500);
  delay(1000);
  digitalWrite(RED_LED, LOW);

  WiFiManager wm;
  wm.resetSettings();

  Serial.println("WiFi cleared! Restarting...");
  lcd.clear();
  lcd.print("WiFi Cleared!");
  lcd.setCursor(0, 1);
  lcd.print("Restarting...");
  delay(2000);
  ESP.restart();
}

// ==================== SUPPORT FUNCTIONS ====================
void testHardware() {
  Serial.println("Testing LEDs and buzzer...");
  digitalWrite(GREEN_LED,  HIGH); delay(500); digitalWrite(GREEN_LED,  LOW); delay(200);
  digitalWrite(YELLOW_LED, HIGH); delay(500); digitalWrite(YELLOW_LED, LOW); delay(200);
  digitalWrite(RED_LED,    HIGH); delay(500); digitalWrite(RED_LED,    LOW); delay(200);
  tone(BUZZER, 1500, 100); delay(120);
  tone(BUZZER, 2000, 100); delay(120);
  Serial.println("Hardware test OK.");
}

void displayWaitingMessage() {
  digitalWrite(GREEN_LED,  LOW);
  digitalWrite(RED_LED,    LOW);
  digitalWrite(YELLOW_LED, LOW);
  lcd.clear();
  lcd.print("Entry/Exit");
  lcd.setCursor(0, 1);
  lcd.print("Show QR Code");
}

void displayReadyType(bool exitMode) {
  lcd.clear();
  lcd.print(exitMode ? "Ready to EXIT" : "Ready to ENTER");
  lcd.setCursor(0, 1);
  lcd.print("Scanning...");
}

// ==================== WIFIMANAGER ====================
void startWiFiManager() {
  WiFiManager wm;

  lcd.clear();
  lcd.print("Connect to:");
  lcd.setCursor(0, 1);
  lcd.print("QR-Gate-Setup");
  digitalWrite(YELLOW_LED, HIGH);

  Serial.println("WiFiManager starting...");
  Serial.println("If no saved WiFi, connect to: QR-Gate-Setup");
  Serial.println("Then open 192.168.4.1 in browser");
  Serial.println("To reset WiFi anytime: type RESET in Serial Monitor");

  wm.setConfigPortalTimeout(180);
  bool connected = wm.autoConnect("QR-Gate-Setup");

  digitalWrite(YELLOW_LED, LOW);

  if (connected) {
    Serial.println("WiFi connected: " + WiFi.SSID());
    Serial.println("IP: " + WiFi.localIP().toString());
    lcd.clear();
    lcd.print("WiFi Connected!");
    lcd.setCursor(0, 1);
    lcd.print(WiFi.SSID().substring(0, 16));
    digitalWrite(GREEN_LED, HIGH);
    delay(2000);
    digitalWrite(GREEN_LED, LOW);
  } else {
    Serial.println("No WiFi configured — offline mode");
    lcd.clear();
    lcd.print("No WiFi Set");
    lcd.setCursor(0, 1);
    lcd.print("Offline mode");
    digitalWrite(RED_LED, HIGH);
    delay(2000);
    digitalWrite(RED_LED, LOW);
  }
}

// ==================== SETUP ====================
void setup() {
  WRITE_PERI_REG(RTC_CNTL_BROWN_OUT_REG, 0);
  Serial.begin(115200);
  delay(300);
  Serial.println("\n=== ESP32-CAM QR GATE ===");

  xMutex = xSemaphoreCreateMutex();

  pinMode(GREEN_LED,  OUTPUT);
  pinMode(RED_LED,    OUTPUT);
  pinMode(YELLOW_LED, OUTPUT);
  pinMode(BUZZER,     OUTPUT);
  digitalWrite(GREEN_LED,  LOW);
  digitalWrite(RED_LED,    LOW);
  digitalWrite(YELLOW_LED, LOW);

  // ✅ LCD first
  Wire.begin(SDA_PIN, SCL_PIN);
  lcd.init();
  lcd.backlight();
  lcd.clear();
  lcd.print("Booting...");

  testHardware();

  // ✅ WiFiManager second
  startWiFiManager();

  // ✅ Camera last — same as original working code
  Serial.println("Starting QR Reader...");
  reader.setup();
  reader.beginOnCore(1);

  xTaskCreatePinnedToCore(onQrCodeTask, "QRTask", 8192, NULL, 5, NULL, 1);

  delay(150);
  Wire.begin(SDA_PIN, SCL_PIN);
  lcd.init();
  lcd.backlight();
  delay(50);

  displayWaitingMessage();
  Serial.println("SYSTEM READY");
  Serial.println("Type RESET to clear WiFi credentials");
}

// ==================== LOOP ====================
void loop() {
  // ✅ Check for Serial commands (QR input OR reset command)
  if (Serial.available()) {
    String input = Serial.readStringUntil('\n');
    input.trim();

    // ✅ WiFi reset command
    if (input.equalsIgnoreCase("RESET")) {
      resetWiFi();
      return;
    }

    // Manual QR input
    if (input.length() > 3) {
      bool isInside = inside(input);

      bool shouldProcess = false;
      xSemaphoreTake(xMutex, portMAX_DELAY);
      bool gateClosed    = !isGateOpen;
      bool notProcessing = !processingQR;
      bool qrDiff        = (input != lastQRCode);
      if (gateClosed && notProcessing && (qrDiff || isInside)) {
        processingQR  = true;
        shouldProcess = true;
      }
      xSemaphoreGive(xMutex);

      if (shouldProcess) {
        displayReadyType(isInside);
        delay(400);
        Serial.println("Manual QR: " + input);
        processQRCode(input);
        xSemaphoreTake(xMutex, portMAX_DELAY);
        lastQRCode   = input;
        processingQR = false;
        xSemaphoreGive(xMutex);
      }
    }
  }

  bool gateOpenNow = false;
  xSemaphoreTake(xMutex, portMAX_DELAY);
  if (isGateOpen && millis() - gateOpenTime > GATE_OPEN_DURATION) {
    gateOpenNow = true;
  }
  xSemaphoreGive(xMutex);
  if (gateOpenNow) {
    closeGate();
    displayWaitingMessage();
  }

  if (lastDisplayTime && millis() - lastDisplayTime > DISPLAY_DURATION) {
    xSemaphoreTake(xMutex, portMAX_DELAY);
    bool gateClosed = !isGateOpen;
    xSemaphoreGive(xMutex);
    if (gateClosed) {
      displayWaitingMessage();
      lastDisplayTime = 0;
    }
  }

  delay(50);
}

// ==================== QR PROCESSING ====================
void processQRCode(String qrCode) {
  Serial.print("DEBUG QR: '"); Serial.print(qrCode);
  Serial.print("' Len: "); Serial.println(qrCode.length());

  if (qrCode.length() == 0) { handleInvalidQR(); return; }

  xSemaphoreTake(xMutex, portMAX_DELAY);
  bool isInside = inside(qrCode);
  xSemaphoreGive(xMutex);

  if (isInside) {
    handleExit(qrCode);
    return;
  }

  lcd.clear();
  lcd.print("Checking...");
  digitalWrite(GREEN_LED,  LOW);
  digitalWrite(RED_LED,    LOW);
  digitalWrite(YELLOW_LED, HIGH);

  if (WiFi.status() != WL_CONNECTED) {
    digitalWrite(GREEN_LED,  LOW);
    digitalWrite(YELLOW_LED, LOW);
    digitalWrite(RED_LED,    HIGH);
    tone(BUZZER, 300, 800);
    lcd.clear();
    lcd.print("No WiFi!");
    lastDisplayTime = millis();
    return;
  }

  WiFiClientSecure client;
  client.setInsecure();
  HTTPClient http;
  http.setTimeout(10000);
  http.begin(client, String(serverURL) + "?qr=" + qrCode);

  int httpCode = http.GET();
  Serial.print("HTTP: "); Serial.println(httpCode);

  if (httpCode == 200) {
    String response = http.getString();
    DynamicJsonDocument doc(1024);

    if (deserializeJson(doc, response) == DeserializationError::Ok) {
      const char* statusChar = doc["status"];
      String status = String(statusChar ? statusChar : "");

      if (status == "Inside" || status == "Valid") {
        handleValidQR(doc, qrCode);
      } else if (status == "AlreadyExited") {
        handleAlreadyExited(doc);
      } else if (status == "Expired") {
        handleExpiredQR();
      } else {
        handleInvalidQR();
      }
    } else {
      handleInvalidQR();
    }
  } else {
    handleInvalidQR();
  }

  http.end();
  lastDisplayTime = millis();
}

// ==================== RESPONSE HANDLERS ====================
void handleValidQR(DynamicJsonDocument& doc, String qrCode) {
  digitalWrite(GREEN_LED,  HIGH);
  digitalWrite(YELLOW_LED, LOW);
  tone(BUZZER, 1200, 100); delay(150);
  tone(BUZZER, 1800, 150);

  String name = doc["visitor_name"] | "Guest";
  lcd.clear();
  lcd.print("Welcome!");
  lcd.setCursor(0, 1);
  lcd.print(name.substring(0, 16));

  recordEntry(qrCode, name);
  delay(1000);
  openGate();
  lastDisplayTime = millis();
}

void handleAlreadyExited(DynamicJsonDocument& doc) {
  digitalWrite(GREEN_LED,  LOW);
  digitalWrite(YELLOW_LED, LOW);
  digitalWrite(RED_LED,    HIGH);
  tone(BUZZER, 300, 300); delay(350);
  tone(BUZZER, 300, 300);

  String name = doc["visitor_name"] | "Visitor";
  lcd.clear();
  lcd.print("Re-entry Denied");
  lcd.setCursor(0, 1);
  lcd.print(name.substring(0, 16));

  lastDisplayTime = millis();
}

void handleExpiredQR() {
  digitalWrite(GREEN_LED,  LOW);
  digitalWrite(RED_LED,    LOW);
  digitalWrite(YELLOW_LED, HIGH);
  tone(BUZZER, 800, 400);
  lcd.clear();
  lcd.print("EXPIRED QR");
  lastDisplayTime = millis();
}

void handleInvalidQR() {
  digitalWrite(GREEN_LED,  LOW);
  digitalWrite(YELLOW_LED, LOW);
  digitalWrite(RED_LED,    HIGH);
  tone(BUZZER, 300, 800);
  lcd.clear();
  lcd.print("INVALID QR");
  lastDisplayTime = millis();
}
