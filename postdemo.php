<?php
header("Content-Type: application/json; charset=UTF-8");

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Database connection
    $conn = new mysqli(
        "localhost",
        "root",
        "",
        "espdemo2"
    );

    $conn->set_charset("utf8mb4");

    // Accept POST requests only
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        http_response_code(405);
        echo json_encode([
            "status" => "error",
            "message" => "POST request required."
        ]);
        exit;
    }

    // Receive device credentials
    $deviceID = trim($_POST["device_id"] ?? "");
    $apiToken = trim($_POST["api_token"] ?? "");

    // Check credentials are present
    if ($deviceID === "" || $apiToken === "") {
        http_response_code(401);
        echo json_encode([
            "status" => "error",
            "message" => "Device credentials missing."
        ]);
        exit;
    }

    // Find device and verify its authorization
    $stmt = $conn->prepare(
        "SELECT token_hash, enabled
         FROM esp_devices
         WHERE device_id = ?
         LIMIT 1"
    );

    $stmt->bind_param("s", $deviceID);
    $stmt->execute();
    $result = $stmt->get_result();
    $device = $result->fetch_assoc();
    $stmt->close();

    if (
        !$device ||
        (int)$device["enabled"] !== 1 ||
        !hash_equals(
            $device["token_hash"],
            hash("sha256", $apiToken)
        )
    ) {
        http_response_code(403);
        echo json_encode([
            "status" => "error",
            "message" => "Device is not authorized."
        ]);
        exit;
    }

    // Receive sensor values and time information
    $value1 = $_POST["value1"] ?? "";
    $value2 = $_POST["value2"] ?? "";
    $value3 = $_POST["value3"] ?? "";
    $timestamp = $_POST["timestamp"] ?? "";
    $timezone = trim($_POST["timezone"] ?? "");

    // Validate the installation timezone
    $validTimezones = DateTimeZone::listIdentifiers();

    if (!in_array($timezone, $validTimezones, true)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Invalid timezone."
        ]);
        exit;
    }

    // Validate UTC Unix timestamp
    if (
        !ctype_digit((string)$timestamp) ||
        (float)$timestamp < 1700000000
    ) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Invalid timestamp."
        ]);
        exit;
    }

    // Convert UTC timestamp to installation-local time
    $utcDateTime = new DateTimeImmutable("@".$timestamp);

    $localDateTime = $utcDateTime->setTimezone(
        new DateTimeZone($timezone)
    );

    $date = $localDateTime->format("Y-m-d");
    $time = $localDateTime->format("H:i:s");

    $utcTimestamp = $utcDateTime
        ->setTimezone(new DateTimeZone("UTC"))
        ->format("Y-m-d H:i:s");

    // Save only after successful authorization
    $sql = "INSERT INTO logs
        (device_id, value1, value2, value3,
         `Date`, `Time`, `TimeStamp`, timezone)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        "ssssssss",
        $deviceID,
        $value1,
        $value2,
        $value3,
        $date,
        $time,
        $utcTimestamp,
        $timezone
    );

    $stmt->execute();
    $recordID = $conn->insert_id;

    echo json_encode([
        "status" => "success",
        "message" => "Authorized data saved.",
        "id" => $recordID,
        "device_id" => $deviceID,
        "value1" => $value1,
        "value2" => $value2,
        "value3" => $value3,
        "Date" => $date,
        "Time" => $time,
        "TimeStamp_UTC" => $utcTimestamp,
        "timezone" => $timezone
    ], JSON_PRETTY_PRINT);

    $stmt->close();
    $conn->close();

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Server/database error."
    ]);
}
?>
