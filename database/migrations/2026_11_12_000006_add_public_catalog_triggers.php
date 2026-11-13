<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_saved_books_require_public');
        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_saved_books_require_public
BEFORE INSERT ON saved_books
FOR EACH ROW
BEGIN
  DECLARE v_is_public TINYINT;
  SELECT is_public INTO v_is_public FROM books WHERE id = NEW.book_id;
  IF v_is_public IS NULL OR v_is_public <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only public books can be saved';
  END IF;
END
SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS trg_books_keep_saved_links_public');
        DB::unprepared(<<<'SQL'
CREATE TRIGGER trg_books_keep_saved_links_public
BEFORE UPDATE ON books
FOR EACH ROW
BEGIN
  IF OLD.is_public = 1 AND NEW.is_public = 0 AND EXISTS (
    SELECT 1 FROM saved_books WHERE book_id = OLD.id
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Remove saved links before unpublishing a book';
  END IF;
END
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_books_keep_saved_links_public');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_saved_books_require_public');
    }
};
