<?php
function updateCurrency($conn, $user_id, $amount, $type, $desc) {
  // Update balance
  $conn->query("INSERT INTO user_currency (user_id, balance)
                VALUES ($user_id, $amount)
                ON DUPLICATE KEY UPDATE balance = balance + $amount");

  // Record transaction
  $stmt = $conn->prepare("INSERT INTO currency_transactions (user_id, type, amount, description)
                          VALUES (?, ?, ?, ?)");
  $stmt->bind_param("isis", $user_id, $type, $amount, $desc);
  $stmt->execute();
}
