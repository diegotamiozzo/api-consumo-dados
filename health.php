<?php
// Health check para o Render: não toca no banco nem exige API key.
header('Content-Type: application/json; charset=UTF-8');
echo json_encode(['status' => 'ok']);
