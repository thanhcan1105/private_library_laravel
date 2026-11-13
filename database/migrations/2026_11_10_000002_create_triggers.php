<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_reading_sessions_update_progress');
        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_reading_sessions_update_progress
AFTER INSERT ON reading_sessions
FOR EACH ROW
BEGIN
  INSERT INTO reading_progress (user_id, book_id, last_read_at)
  VALUES (NEW.user_id, NEW.book_id, IFNULL(NEW.ended_at, NEW.started_at))
  ON DUPLICATE KEY UPDATE last_read_at = IFNULL(NEW.ended_at, NEW.started_at);
END
SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS trg_books_check_quota');
        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_books_check_quota
BEFORE INSERT ON books
FOR EACH ROW
BEGIN
  DECLARE v_used BIGINT;
  DECLARE v_limit BIGINT;
  SELECT IFNULL(SUM(file_size), 0) INTO v_used FROM books WHERE user_id = NEW.user_id;
  SELECT storage_limit INTO v_limit FROM users WHERE id = NEW.user_id;
  IF v_used + NEW.file_size > v_limit THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Storage limit exceeded';
  END IF;
END
SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS trg_book_shares_check_owner');
        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_book_shares_check_owner
BEFORE INSERT ON book_shares
FOR EACH ROW
BEGIN
  DECLARE v_owner BIGINT;
  SELECT user_id INTO v_owner FROM books WHERE id = NEW.book_id;
  IF v_owner IS NULL OR v_owner <> NEW.owner_id THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only the owner can share a book';
  END IF;
END
SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS trg_users_revoke_shares_on_lock');
        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_users_revoke_shares_on_lock
AFTER UPDATE ON users
FOR EACH ROW
BEGIN
  IF OLD.status = 1 AND NEW.status = 0 THEN
    DELETE FROM book_shares WHERE owner_id = NEW.id;
  END IF;
END
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_reading_sessions_update_progress');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_books_check_quota');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_book_shares_check_owner');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_users_revoke_shares_on_lock');
    }
};