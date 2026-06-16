#include <WiFi.h>
#include <HTTPClient.h>
#include <DHT.h>
#define DHTPIN 4 // Pin sensor DHT22 terhubung
#define DHTTYPE DHT22 // Tipe sensor
DHT dht(DHTPIN, DHTTYPE);
const char* ssid = "NAMA HOSTSPOT";
const char* password = "PASSWORDNYA";
const char* serverURL = "HTTP://SERVER DARI FLASK VSCODE";
void setup() {
Serial.begin(115200);
WiFi.begin(ssid, password);
dht.begin();
while (WiFi.status() != WL_CONNECTED) {
delay(1000);
Serial.println("Connecting to WiFi...");
}
Serial.println("Connected to WiFi.");
}
void loop() {
delay(5000);
float humidity = dht.readHumidity();
float temperature = dht.readTemperature();
if (isnan(humidity) || isnan(temperature)) {
Serial.println("Failed to read from DHT sensor!");
return;
}
Serial.printf("Humidity: %.2f%%, Temperature: %.2f°C\n", humidity,
temperature);
if (WiFi.status() == WL_CONNECTED) {
HTTPClient http;
http.begin(serverURL);
http.addHeader("Content-Type", "application/json");
String payload = "{\"humidity\": " + String(humidity) + ",
\"temperature\": " + String(temperature) + "}";
int httpResponseCode = http.POST(payload);
if (httpResponseCode > 0) {
String response = http.getString();
Serial.println("Server Response: " + response);
} else {
Serial.print("Error code: ");

Serial.println(httpResponseCode);
}
http.end();
}
}