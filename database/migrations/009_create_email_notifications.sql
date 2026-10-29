-- 009: database-backed owner email queue (one row per payment event)
CREATE TABLE IF NOT EXISTS email_notifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    notification_type VARCHAR(40) NOT NULL,
    order_id INT UNSIGNED NULL,
    payment_id BIGINT UNSIGNED NULL,
    recipient VARCHAR(190) NOT NULL,
    language ENUM('bn', 'en') NOT NULL DEFAULT 'bn',
    subject VARCHAR(190) NOT NULL,
    body MEDIUMTEXT NOT NULL,
    status ENUM('queued', 'sent', 'failed') NOT NULL DEFAULT 'queued',
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    last_error VARCHAR(500) NULL,
    next_retry_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_email_event (payment_id, notification_type),
    KEY idx_email_status (status, next_retry_at),
    CONSTRAINT fk_email_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE SET NULL,
    CONSTRAINT fk_email_payment FOREIGN KEY (payment_id) REFERENCES payments (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
