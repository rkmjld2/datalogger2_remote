#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>
#include <time.h>

// =================================================
// 1. WI-FI SETTINGS
// =================================================

const char *ssid = "Airtel_56";
const char *password = "Raviuma5658";

// =================================================
// 2. FIXED DEVICE ID AND AUTHENTICATION TOKEN
// =================================================

// This ID is fixed in the firmware.
// Do not generate it from the ESP8266 chip ID.

const char *deviceID = "ESP0001";

// IMPORTANT:
// Replace this example placeholder with your own
// long random secret. Register the same secret in
// the esp_devices database table.

const char *apiToken =
  "EspSwitch52026";

// =================================================
// 3. LOCAL XAMPP PHP URL
// =================================================

const char *serverURL =
  "http://192.168.1.9/datalogger4/postdemo.php";

// =================================================
// 4. AUTOMATIC GEOLOCATION SERVICE
// =================================================

const char *geoURL =
  "http://ip-api.com/json/?fields=status,timezone";

String installationTimezone = "";

// =================================================
// 5. TIMING SETTINGS
// =================================================

const unsigned long sendInterval = 5000UL;

const unsigned long timezoneRefreshInterval =
  6UL * 60UL * 60UL * 1000UL;

const unsigned long timezoneRetryInterval =
  5UL * 60UL * 1000UL;

unsigned long previousSendTime = 0;
unsigned long previousTimezoneCheck = 0;

// =================================================
// 6. TEST VALUES
// =================================================

int adcvalue1 = 0;
int adcvalue2 = 0;
int adcvalue3 = 0;

// =================================================
// 7. URL ENCODING
// =================================================

String urlEncode(const String &input) {

  String output = "";
  const char hex[] = "0123456789ABCDEF";

  for (unsigned int i = 0; i < input.length(); i++) {

    unsigned char c = input[i];

    if ((c >= 'A' && c <= 'Z') ||
        (c >= 'a' && c <= 'z') ||
        (c >= '0' && c <= '9') ||
        c == '-' || c == '_' ||
        c == '.' || c == '~') {

      output += (char)c;

    } else {

      output += '%';
      output += hex[c >> 4];
      output += hex[c & 0x0F];
    }
  }

  return output;
}

// =================================================
// 8. DETECT INSTALLATION TIMEZONE
// =================================================

bool detectTimezone() {

  Serial.println();
  Serial.println("Checking installation timezone...");

  WiFiClient client;
  HTTPClient http;

  if (!http.begin(client, geoURL)) {
    Serial.println("Could not start geolocation request.");
    return false;
  }

  http.setTimeout(10000);

  int httpCode = http.GET();

  if (httpCode != HTTP_CODE_OK) {

    Serial.print("Geolocation HTTP error: ");
    Serial.println(httpCode);

    http.end();
    return false;
  }

  String response = http.getString();
  http.end();

  Serial.println("Geolocation response:");
  Serial.println(response);

  if (response.indexOf("\"status\":\"success\"") < 0) {
    Serial.println("Geolocation request was unsuccessful.");
    return false;
  }

  String key = "\"timezone\":\"";
  int start = response.indexOf(key);

  if (start < 0) {
    Serial.println("Timezone field not found.");
    return false;
  }

  start += key.length();

  int finish = response.indexOf("\"", start);

  if (finish < 0) {
    Serial.println("Invalid timezone response.");
    return false;
  }

  String detected = response.substring(start, finish);
  detected.trim();

  if (detected.length() == 0 ||
      detected.indexOf('/') < 0) {

    Serial.println("Invalid timezone returned.");
    return false;
  }

  installationTimezone = detected;

  Serial.print("Detected timezone: ");
  Serial.println(installationTimezone);

  return true;
}

// =================================================
// 9. SYNCHRONIZE UTC TIME USING NTP
// =================================================

bool synchronizeTime() {

  Serial.println();
  Serial.println("Synchronizing UTC time using NTP...");

  configTime(
    0,
    0,
    "pool.ntp.org",
    "time.nist.gov"
  );

  for (int i = 0; i < 30; i++) {

    time_t now = time(nullptr);

    if (now > 1700000000) {

      Serial.println("NTP synchronization successful.");

      Serial.print("UTC Unix timestamp: ");
      Serial.println((unsigned long)now);

      return true;
    }

    Serial.print(".");
    delay(1000);
  }

  Serial.println();
  Serial.println("NTP synchronization failed.");

  return false;
}

// =================================================
// 10. SETUP
// =================================================

void setup() {

  Serial.begin(115200);
  delay(1000);

  Serial.println();
  Serial.println("==================================");
  Serial.println("AUTHORIZED ESP8266 DATA LOGGER");
  Serial.println("Fixed Device ID: ESP0001");
  Serial.println("Local XAMPP test");
  Serial.println("==================================");

  WiFi.mode(WIFI_STA);
  WiFi.begin(ssid, password);

  Serial.print("Connecting to Wi-Fi");

  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }

  Serial.println();
  Serial.println("Wi-Fi connected.");

  Serial.print("ESP8266 IP address: ");
  Serial.println(WiFi.localIP());

  Serial.print("Fixed device ID: ");
  Serial.println(deviceID);

  Serial.print("PHP server: ");
  Serial.println(serverURL);

  bool timezoneOK = detectTimezone();

  previousTimezoneCheck = millis();

  if (!timezoneOK) {
    Serial.println("Timezone detection will be retried.");
  }

  synchronizeTime();
}

// =================================================
// 11. MAIN LOOP
// =================================================

void loop() {

  if (WiFi.status() != WL_CONNECTED) {

    Serial.println("Wi-Fi disconnected. Reconnecting...");

    WiFi.reconnect();
    delay(3000);
    return;
  }

  unsigned long nowMillis = millis();

  // -----------------------------------------------
  // Refresh timezone automatically
  // -----------------------------------------------

  unsigned long refreshInterval =
    installationTimezone.length() == 0
      ? timezoneRetryInterval
      : timezoneRefreshInterval;

  if (nowMillis - previousTimezoneCheck >= refreshInterval) {

    previousTimezoneCheck = nowMillis;

    if (!detectTimezone()) {
      Serial.println(
        "Timezone refresh failed; retrying in 5 minutes."
      );
    }
  }

  // -----------------------------------------------
  // Send data every 5 seconds
  // -----------------------------------------------

  if (nowMillis - previousSendTime < sendInterval) {
    return;
  }

  previousSendTime = nowMillis;

  if (installationTimezone.length() == 0) {
    Serial.println("Upload skipped: timezone not detected.");
    return;
  }

  time_t utcTimestamp = time(nullptr);

  if (utcTimestamp <= 1700000000) {

    Serial.println("UTC clock is invalid.");

    if (!synchronizeTime()) {
      Serial.println("Upload skipped: NTP unavailable.");
      return;
    }

    utcTimestamp = time(nullptr);
  }

  // -----------------------------------------------
  // Prepare authenticated POST data
  // -----------------------------------------------

  String postData =
    "device_id=" + urlEncode(String(deviceID)) +
    "&api_token=" + urlEncode(String(apiToken)) +
    "&value1=" + String(adcvalue1) +
    "&value2=" + String(adcvalue2) +
    "&value3=" + String(adcvalue3) +
    "&timestamp=" + String((unsigned long)utcTimestamp) +
    "&timezone=" + urlEncode(installationTimezone);

  Serial.println();
  Serial.println("----------------------------------");
  Serial.println("Sending authenticated data");

  Serial.print("Device ID: ");
  Serial.println(deviceID);

  Serial.print("UTC timestamp: ");
  Serial.println((unsigned long)utcTimestamp);

  Serial.print("Timezone: ");
  Serial.println(installationTimezone);

  // Do NOT print postData: it contains the secret token.

  WiFiClient client;
  HTTPClient http;

  if (http.begin(client, serverURL)) {

    http.setTimeout(10000);

    http.addHeader(
      "Content-Type",
      "application/x-www-form-urlencoded"
    );

    int httpCode = http.POST(postData);

    Serial.print("HTTP response code: ");
    Serial.println(httpCode);

    if (httpCode > 0) {

      Serial.println("Server response:");
      Serial.println(http.getString());

    } else {

      Serial.print("HTTP error: ");
      Serial.println(http.errorToString(httpCode));
    }

    http.end();

  } else {

    Serial.println("Could not initialize HTTP connection.");
  }

  // Change sample values for the next test.
  adcvalue1 += 10;
  adcvalue2 += 20;
  adcvalue3 += 30;
}