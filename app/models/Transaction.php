<?php
// Transaction model to handle insertion, retrieval, and deletion of financial transactions
class Transaction
{

    private $pdo;

    // Constructor injects PDO database connection dependency
    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    // Insert a new income or expense transaction into the database
    public function add($user_id, $type, $amount, $description, $category, $account_type = 'Main')
    {
        $stmt = $this->pdo->prepare("INSERT INTO transactions (user_id, type, amount, description, category, account_type) VALUES (?, ?, ?, ?, ?, ?)");
        return $stmt->execute([$user_id, $type, $amount, $description, $category, $account_type]);
    }

    // Delete a specific transaction ensuring it belongs to the active user
    public function delete($id, $user_id)
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM transactions WHERE id = ? AND user_id = ?"
        );
        return $stmt->execute([$id, $user_id]);
    }

    // Retrieve all transactions for a user, or filtered by a specific wallet/account type
    public function getByUser($user_id, $account_type = null)
    {
        if ($account_type) {
            $stmt = $this->pdo->prepare(
                "SELECT * FROM transactions 
                 WHERE user_id = ? AND account_type = ? 
                 ORDER BY created_at DESC"
            );
            $stmt->execute([$user_id, $account_type]);
        } else {
            $stmt = $this->pdo->prepare(
                "SELECT * FROM transactions 
                 WHERE user_id = ? 
                 ORDER BY created_at DESC"
            );
            $stmt->execute([$user_id]);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Fetch a single transaction by ID to display or edit its details
    public function getById($id, $user_id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM transactions WHERE id=? AND user_id=?");
        $stmt->execute([$id, $user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Update an existing transaction with modified data
    public function update($id, $user_id, $type, $amount, $description, $category, $account_type = 'Main')
    {
        $stmt = $this->pdo->prepare("
        UPDATE transactions 
        SET type=?, amount=?, description=?, category=?, account_type=? 
        WHERE id=? AND user_id=?
    ");
        return $stmt->execute([$type, $amount, $description, $category, $account_type, $id, $user_id]);
    }
}
