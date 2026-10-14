<?php
namespace App\Services;

use App\Config\Database;
use Exception;
use PDO;

class WalletService
{
    /**
     * Get or create wallet for user with atomic transaction and row-level locking
     */
    public static function getOrCreateWallet(int $userId): array
    {
        $db = Database::getConnection();
        $inTxn = $db->inTransaction();
        if (!$inTxn) {
            $db->beginTransaction();
        }

        try {
            $stmt = $db->prepare("SELECT id, user_id, balance, total_credited, total_debited FROM wallets WHERE user_id = ? FOR UPDATE");
            $stmt->execute([$userId]);
            $wallet = $stmt->fetch();

            if (!$wallet) {
                // Concurrency-safe insert using ON DUPLICATE KEY UPDATE to prevent race condition crashes
                $insert = $db->prepare("
                    INSERT INTO wallets (user_id, balance, total_credited, total_debited, created_at, updated_at)
                    VALUES (?, 0.00, 0.00, 0.00, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE updated_at = NOW()
                ");
                $insert->execute([$userId]);

                $stmt->execute([$userId]);
                $wallet = $stmt->fetch();
            }

            if (!$inTxn) {
                $db->commit();
            }

            return $wallet;
        } catch (Exception $e) {
            if (!$inTxn && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Get current balance
     */
    public static function getBalance(int $userId): float
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT balance FROM wallets WHERE user_id = ?");
        $stmt->execute([$userId]);
        $bal = $stmt->fetchColumn();
        return $bal !== false ? (float)$bal : 0.00;
    }

    /**
     * Atomic Credit to Wallet
     */
    public static function credit(
        int $userId,
        float $amount,
        string $type,
        string $referenceType,
        string $referenceId,
        string $idempotencyKey,
        string $descriptionEn,
        string $descriptionHi,
        ?string $remarks = null,
        ?int $createdByUserId = null
    ): array {
        if ($amount <= 0) {
            throw new Exception("Credit amount must be greater than zero.");
        }

        $db = Database::getConnection();
        $inTxn = $db->inTransaction();
        if (!$inTxn) {
            $db->beginTransaction();
        }

        try {
            // 1. Lock wallet row FIRST inside active transaction to serialize all operations for this user
            $wallet = self::getOrCreateWallet($userId);
            $walletId = (int)$wallet['id'];

            // 2. Check idempotency under lock to prevent double credits
            $chk = $db->prepare("SELECT id, txn_ref, balance_after FROM wallet_transactions WHERE idempotency_key = ? LIMIT 1 FOR UPDATE");
            $chk->execute([$idempotencyKey]);
            $existing = $chk->fetch();
            if ($existing) {
                if (!$inTxn) $db->commit();
                return [
                    'success' => true,
                    'already_processed' => true,
                    'txn_ref' => $existing['txn_ref'],
                    'new_balance' => (float)$existing['balance_after']
                ];
            }

            // Fresh locked balance from wallet row
            $balanceBefore = (float)$wallet['balance'];
            $balanceAfter = round($balanceBefore + $amount, 2);

            // Generate unique txn reference
            $txnRef = 'TXN-' . date('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));

            // Update wallet balance atomically
            $upd = $db->prepare("
                UPDATE wallets
                SET balance = balance + ?,
                    total_credited = total_credited + ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $upd->execute([$amount, $amount, $walletId]);

            // Insert immutable transaction ledger row
            $ins = $db->prepare("
                INSERT INTO wallet_transactions (
                    txn_ref, user_id, wallet_id, type, direction, amount,
                    balance_before, balance_after, status, reference_type,
                    reference_id, idempotency_key, description_en, description_hi,
                    remarks, created_by_user_id, created_at
                ) VALUES (
                    ?, ?, ?, ?, 'CREDIT', ?,
                    ?, ?, 'SUCCESS', ?,
                    ?, ?, ?, ?,
                    ?, ?, NOW()
                )
            ");
            $ins->execute([
                $txnRef,
                $userId,
                $walletId,
                $type,
                $amount,
                $balanceBefore,
                $balanceAfter,
                $referenceType,
                $referenceId,
                $idempotencyKey,
                $descriptionEn,
                $descriptionHi,
                $remarks,
                $createdByUserId
            ]);

            // Audit
            AuditService::log(
                'WALLET_CREDIT',
                'WALLET',
                (string)$walletId,
                [
                    'user_id' => $userId,
                    'amount' => $amount,
                    'type' => $type,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                    'txn_ref' => $txnRef
                ],
                $createdByUserId
            );

            if (!$inTxn) {
                $db->commit();
            }

            return [
                'success' => true,
                'txn_ref' => $txnRef,
                'previous_balance' => $balanceBefore,
                'new_balance' => $balanceAfter,
                'amount' => $amount
            ];
        } catch (Exception $e) {
            if (!$inTxn && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Atomic Debit from Wallet (Never allows negative balance)
     */
    public static function debit(
        int $userId,
        float $amount,
        string $type,
        string $referenceType,
        string $referenceId,
        string $idempotencyKey,
        string $descriptionEn,
        string $descriptionHi,
        ?string $remarks = null,
        ?int $createdByUserId = null
    ): array {
        if ($amount <= 0) {
            throw new Exception("Debit amount must be greater than zero.");
        }

        $db = Database::getConnection();
        $inTxn = $db->inTransaction();
        if (!$inTxn) {
            $db->beginTransaction();
        }

        try {
            // 1. Lock wallet row FIRST inside active transaction to serialize all operations for this user
            $wallet = self::getOrCreateWallet($userId);
            $walletId = (int)$wallet['id'];

            // 2. Check idempotency under lock to prevent double debits
            $chk = $db->prepare("SELECT id, txn_ref, balance_after FROM wallet_transactions WHERE idempotency_key = ? LIMIT 1 FOR UPDATE");
            $chk->execute([$idempotencyKey]);
            $existing = $chk->fetch();
            if ($existing) {
                if (!$inTxn) $db->commit();
                return [
                    'success' => true,
                    'already_processed' => true,
                    'txn_ref' => $existing['txn_ref'],
                    'new_balance' => (float)$existing['balance_after']
                ];
            }

            $balanceBefore = (float)$wallet['balance'];

            if ($balanceBefore < $amount) {
                throw new Exception("Insufficient wallet balance (Current: ₹" . number_format($balanceBefore, 2) . ", Required: ₹" . number_format($amount, 2) . "). Please recharge your wallet.");
            }

            $balanceAfter = round($balanceBefore - $amount, 2);
            $txnRef = 'TXN-' . date('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));

            // Deduct atomically with balance constraint
            $upd = $db->prepare("
                UPDATE wallets
                SET balance = balance - ?,
                    total_debited = total_debited + ?,
                    updated_at = NOW()
                WHERE id = ? AND balance >= ?
            ");
            $upd->execute([$amount, $amount, $walletId, $amount]);

            if ($upd->rowCount() === 0) {
                throw new Exception("Wallet balance deduction race condition prevented. Please try again.");
            }

            // Insert transaction row
            $ins = $db->prepare("
                INSERT INTO wallet_transactions (
                    txn_ref, user_id, wallet_id, type, direction, amount,
                    balance_before, balance_after, status, reference_type,
                    reference_id, idempotency_key, description_en, description_hi,
                    remarks, created_by_user_id, created_at
                ) VALUES (
                    ?, ?, ?, ?, 'DEBIT', ?,
                    ?, ?, 'SUCCESS', ?,
                    ?, ?, ?, ?,
                    ?, ?, NOW()
                )
            ");
            $ins->execute([
                $txnRef,
                $userId,
                $walletId,
                $type,
                $amount,
                $balanceBefore,
                $balanceAfter,
                $referenceType,
                $referenceId,
                $idempotencyKey,
                $descriptionEn,
                $descriptionHi,
                $remarks,
                $createdByUserId
            ]);

            // Audit
            AuditService::log(
                'WALLET_DEBIT',
                'WALLET',
                (string)$walletId,
                [
                    'user_id' => $userId,
                    'amount' => $amount,
                    'type' => $type,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                    'txn_ref' => $txnRef
                ],
                $createdByUserId
            );

            if (!$inTxn) {
                $db->commit();
            }

            return [
                'success' => true,
                'txn_ref' => $txnRef,
                'previous_balance' => $balanceBefore,
                'new_balance' => $balanceAfter,
                'amount' => $amount
            ];
        } catch (Exception $e) {
            if (!$inTxn && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Passbook query with filters and pagination
     */
    public static function getPassbook(
        int $userId,
        int $limit = 50,
        int $offset = 0,
        array $filters = []
    ): array {
        $db = Database::getConnection();
        $where = ["user_id = :user_id"];
        $params = [':user_id' => $userId];

        if (!empty($filters['direction'])) {
            $where[] = "direction = :direction";
            $params[':direction'] = $filters['direction'];
        }

        if (!empty($filters['type'])) {
            $where[] = "type = :type";
            $params[':type'] = $filters['type'];
        }

        if (!empty($filters['start_date'])) {
            $where[] = "created_at >= :start_date";
            $params[':start_date'] = $filters['start_date'] . ' 00:00:00';
        }

        if (!empty($filters['end_date'])) {
            $where[] = "created_at <= :end_date";
            $params[':end_date'] = $filters['end_date'] . ' 23:59:59';
        }

        $whereClause = implode(' AND ', $where);

        // Count total
        $cntStmt = $db->prepare("SELECT COUNT(*) FROM wallet_transactions WHERE {$whereClause}");
        $cntStmt->execute($params);
        $total = (int)$cntStmt->fetchColumn();

        // Fetch rows
        $sql = "
            SELECT id, txn_ref, type, direction, amount, balance_before, balance_after,
                   status, reference_type, reference_id, description_en, description_hi,
                   remarks, created_at
            FROM wallet_transactions
            WHERE {$whereClause}
            ORDER BY id DESC
            LIMIT {$limit} OFFSET {$offset}
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return [
            'total' => $total,
            'transactions' => $rows,
            'limit' => $limit,
            'offset' => $offset
        ];
    }
}
