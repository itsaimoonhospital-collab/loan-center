<?php
// get_patient_data.php
include('db_connect.php');
header('Content-Type: application/json; charset=utf-8');

$id_card = isset($_GET['id_card']) ? trim($_GET['id_card']) : '';

if (!empty($id_card)) {
    $stmt = $conn->prepare("SELECT * FROM patients WHERE id_card = ? LIMIT 1");
    $stmt->bind_param("s", $id_card);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $patient = $result->fetch_assoc();
        echo json_encode($patient);
    } else {
        echo json_encode(null);
    }
    $stmt->close();
} else {
    echo json_encode(['error' => 'No ID Provided']);
}
$conn->close();