<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BookShareController extends Controller
{
    public function sharedWithMe(): View
    {
        $shares = DB::table('book_shares')
            ->join('books', 'book_shares.book_id', '=', 'books.id')
            ->join('users as owners', 'book_shares.owner_id', '=', 'owners.id')
            ->leftJoin('authors', 'books.author_id', '=', 'authors.id')
            ->where('book_shares.recipient_id', Auth::id())
            ->where(function ($query) {
                $query->whereNull('book_shares.expires_at')
                    ->orWhere('book_shares.expires_at', '>', now());
            })
            ->orderByDesc('book_shares.created_at')
            ->paginate(10, [
                'book_shares.id as share_id',
                'book_shares.permission',
                'book_shares.expires_at',
                'books.id as book_id',
                'books.title',
                'books.cover_path',
                'authors.name as author_name',
                'owners.full_name as owner_name',
                'owners.email as owner_email',
            ]);

        return view('books.shared', compact('shares'));
    }

    public function manage(int $bookId): View
    {
        $book = $this->ownedBook($bookId);
        $shares = DB::table('book_shares')
            ->join('users as recipients', 'book_shares.recipient_id', '=', 'recipients.id')
            ->where('book_shares.book_id', $bookId)
            ->where('book_shares.owner_id', Auth::id())
            ->orderByDesc('book_shares.created_at')
            ->get([
                'book_shares.id',
                'book_shares.permission',
                'book_shares.created_at',
                'book_shares.expires_at',
                'recipients.full_name as recipient_name',
                'recipients.email as recipient_email',
            ]);

        return view('books.shares', compact('book', 'shares'));
    }

    public function store(Request $request, int $bookId): RedirectResponse
    {
        $book = $this->ownedBook($bookId);
        $data = $request->validate([
            'recipient_email' => ['required', 'email', 'max:100', 'exists:users,email'],
            'permission' => ['required', 'string', 'in:read,read_annotate'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ], [
            'required' => 'Vui lòng nhập :attribute.',
            'email' => ':Attribute không đúng định dạng.',
            'max' => ':Attribute không được vượt quá :max ký tự.',
            'exists' => 'Không tìm thấy tài khoản có email này.',
            'permission.in' => 'Quyền chia sẻ không hợp lệ.',
            'expires_at.date' => 'Thời hạn chia sẻ không hợp lệ.',
            'expires_at.after' => 'Thời hạn phải ở tương lai.',
        ], [
            'recipient_email' => 'email người nhận',
            'permission' => 'quyền chia sẻ',
            'expires_at' => 'thời hạn chia sẻ',
        ]);

        $recipient = DB::table('users')
            ->where('email', $data['recipient_email'])
            ->first(['id', 'status']);

        if ((int) $recipient->id === (int) Auth::id()) {
            return back()->withErrors(['recipient_email' => 'Bạn không thể chia sẻ sách cho chính mình.'])->withInput();
        }

        if ((int) $recipient->status === 0) {
            return back()->withErrors(['recipient_email' => 'Không thể chia sẻ sách cho tài khoản đang bị khóa.'])->withInput();
        }

        $existingShare = DB::table('book_shares')
            ->where('book_id', $book->id)
            ->where('recipient_id', $recipient->id)
            ->exists();

        if ($existingShare) {
            return back()->withErrors(['recipient_email' => 'Sách đã được chia sẻ cho tài khoản này. Hãy thu hồi lượt chia sẻ cũ trước khi tạo lượt mới.'])->withInput();
        }

        try {
            DB::table('book_shares')->insert([
                'book_id' => $book->id,
                'owner_id' => Auth::id(),
                'recipient_id' => $recipient->id,
                'permission' => $data['permission'],
                'expires_at' => ! empty($data['expires_at'])
                    ? Carbon::parse($data['expires_at'])->format('Y-m-d H:i:s')
                    : null,
            ]);
        } catch (QueryException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
                return back()->withErrors(['recipient_email' => 'Sách đã được chia sẻ cho tài khoản này.'])->withInput();
            }

            if ((int) ($exception->errorInfo[1] ?? 0) === 1644) {
                return back()->withErrors(['recipient_email' => 'Chỉ chủ sở hữu sách mới có thể chia sẻ.'])->withInput();
            }

            throw $exception;
        }

        return redirect()->route('books.shares.manage', $book->id)->with('status', 'Đã chia sẻ sách thành công.');
    }

    public function destroy(int $bookId, int $shareId): RedirectResponse
    {
        $book = $this->ownedBook($bookId);
        $deleted = DB::table('book_shares')
            ->where('id', $shareId)
            ->where('book_id', $book->id)
            ->where('owner_id', Auth::id())
            ->delete();

        abort_if($deleted === 0, 404, 'Không tìm thấy lượt chia sẻ.');

        return redirect()->route('books.shares.manage', $book->id)->with('status', 'Đã thu hồi quyền truy cập sách.');
    }

    private function ownedBook(int $bookId): object
    {
        $book = DB::table('books')
            ->where('id', $bookId)
            ->where('user_id', Auth::id())
            ->first(['id', 'title', 'user_id']);

        if (! $book) {
            abort(404, 'Không tìm thấy sách.');
        }

        return $book;
    }
}
