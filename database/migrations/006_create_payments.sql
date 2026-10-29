-- 006: explicit payment records, separate from fulfilment status
CREATE TABLE IF NOT EXISTS payments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id INT UNSIGNED NOT NULL,
    method ENUM('cash', 'cod', 'bkash', 'nagad', 'card', 'other') NOT NULL,
    amount DECIMAL(12, 2) NOT NULL,
    status ENUM('pending', 'confirmed', 'refunded') NOT NULL DEFAULT 'confirmed',
    reference VARCHAR(120) NULL,
    refund_reason VARCHAR(255) NULL,
    recorded_by INT UNSIGNED NULL,
    idempotency_key VARCHAR(80) NOT NULL,
    confirmed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payments_idempotency (idempotency_key),
    KEY idx_payments_order (order_id, status),
    KEY idx_payments_confirmed (status, confirmed_at),
    CONSTRAINT chk_payments_amount CHECK (amount > 0),
    CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
    CONSTRAINT fk_payments_admin FOREIGN KEY (recorded_by) REFERENCES admins (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
