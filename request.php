<?php
session_start();

$service = $_GET["service"] ?? "";

switch ($service) {
    case "barangay_clearance":
    case "clearance":
        header("Location: request_clearance.php");
        exit();
    case "certificate_of_residency":
    case "residency":
        header("Location: request_residency.php");
        exit();
    case "certificate_of_indigency":
    case "indigency":
        header("Location: request_indigency.php");
        exit();
    case "business_clearance":
    case "business":
        header("Location: request_business.php");
        exit();
    default:
        header("Location: services.php");
        exit();
}
