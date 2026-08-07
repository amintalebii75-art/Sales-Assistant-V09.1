-- فاز ۲ هوش مصنوعی — جدول ثبت مصرف. مستقل از app_state؛ به داده‌ی موجود دست نمی‌زند.
-- در phpMyAdmin روی همان دیتابیس اپ Import کن. اگر Import نشود، اپ خودش تلاش می‌کند
-- جدول را بسازد (hippo_ai_ensure_table)، ولی Import دستی مطمئن‌تر است.

CREATE TABLE IF NOT EXISTS ai_requests (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  action_name VARCHAR(60) NOT NULL,
  status_name VARCHAR(20) NOT NULL,
  error_message VARCHAR(500) NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ai_user_date (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
