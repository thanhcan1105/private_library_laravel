@extends('layouts.app')
@section('title', $book->title . ' — Đọc sách')

@section('content')
<div class="container-fluid px-0">
    @if (! $fileExists)
        <div class="alert alert-warning border-warning shadow-sm d-flex align-items-start gap-3 p-4 my-3" role="alert">
            <div class="flex-grow-1">
                <h5 class="alert-heading fw-bold mb-2">Không tìm thấy tệp sách</h5>
                <p class="mb-2">Tệp sách của "<strong>{{ $book->title }}</strong>" không tồn tại trên hệ thống lưu trữ.</p>
                <p class="text-muted small mb-3">Đường dẫn tệp: <code>{{ $book->file_path }}</code></p>
                <a href="{{ route('library') }}" class="btn btn-outline-dark btn-sm">← Quay lại thư viện</a>
            </div>
        </div>
    @else
        <!-- Reader Toolbar -->
        <div id="reader-toolbar" class="card shadow-sm mb-3 border-0">
            <div class="card-body py-2 px-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div class="d-flex align-items-center gap-2">
                    <a href="{{ route('library') }}" class="btn btn-outline-secondary btn-sm" title="Quay lại thư viện">
                        ← Thư viện
                    </a>
                    <div>
                        <h1 class="h6 mb-0 text-truncate" style="max-width: 280px;" title="{{ $book->title }}">
                            {{ $book->title }}
                        </h1>
                        @if ($book->author_name)
                            <small class="text-muted">{{ $book->author_name }}</small>
                        @endif
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <!-- Progress Badge -->
                    <div class="d-flex align-items-center gap-2 me-2" title="Tiến độ đọc">
                        <small class="text-muted d-none d-sm-inline">Tiến độ:</small>
                        <span id="progress-percent-badge" class="badge bg-secondary">
                            {{ $progress && $progress->percent !== null ? number_format((float) $progress->percent, 1) . '%' : '0.0%' }}
                        </span>
                    </div>
                    <span id="reader-page-badge" class="badge bg-light text-dark border" title="Số trang trong chương hiện tại">Trang --/--</span>

                    <button id="btn-bookmark-current" class="btn btn-outline-secondary btn-sm" type="button" title="Lưu vị trí trang hiện tại">
                        🔖 Lưu dấu trang
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="offcanvas" data-bs-target="#readerMarksOffcanvas">
                        📌 Dấu trang & ghi chú
                    </button>

                    <!-- TOC button -->
                    <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="offcanvas" data-bs-target="#tocOffcanvas">
                        📑 Mục lục
                    </button>

                    <!-- Settings button -->
                    <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#settingsModal">
                        ⚙️ Cài đặt
                    </button>

                    <!-- Prev / Next buttons -->
                    <div class="btn-group btn-group-sm ms-2" role="group">
                        <button id="btn-prev" class="btn btn-dark" title="Trang trước (Phím mũi tên trái)">‹ Trước</button>
                        <button id="btn-next" class="btn btn-dark" title="Trang sau (Phím mũi tên phải)">Sau ›</button>
                    </div>
                </div>
            </div>
        </div>

        <div id="reader-mark-feedback" class="alert alert-success py-2 px-3 small d-none" role="status"></div>

        @if (! $progress || ! $progress->location || (float) $progress->percent < 1)
            <div id="reader-tip" class="alert alert-info py-2 px-3 small" role="status">
                Dùng con lăn chuột để chuyển trang. Sách đang mở ở bìa; nhấn <strong>Sau</strong> hoặc phím <kbd>→</kbd> để đọc tiếp. Chương đã đọc sẽ đổi màu trong mục lục.
            </div>
        @endif

        <!-- Reader Viewer Container -->
        <div id="reader-wrapper" class="card shadow-sm border position-relative overflow-hidden" style="background-color: {{ $settings->theme === 'dark' ? '#1a1a1a' : '#ffffff' }};">
            <!-- Floating Navigation Buttons -->
            <button id="floating-prev" class="btn btn-light border shadow-sm position-absolute top-50 start-0 translate-middle-y ms-2 d-none d-md-flex align-items-center justify-content-center" style="z-index: 10; width: 42px; height: 42px; border-radius: 50%; opacity: 0.6;" title="Trang trước">
                ‹
            </button>
            <button id="floating-next" class="btn btn-light border shadow-sm position-absolute top-50 end-0 translate-middle-y me-2 d-none d-md-flex align-items-center justify-content-center" style="z-index: 10; width: 42px; height: 42px; border-radius: 50%; opacity: 0.6;" title="Trang sau">
                ›
            </button>

            <!-- Viewer Element -->
            <div id="viewer" style="height: 75vh; min-height: 520px; width: 100%;"></div>

            <!-- Loading Spinner -->
            <div id="reader-loading" class="position-absolute top-50 start-50 translate-middle text-center" style="z-index: 20;">
                <div class="spinner-border text-primary" role="status"></div>
                <div class="mt-2 text-muted small fw-semibold">Đang mở sách…</div>
            </div>

            <!-- Error State -->
            <div id="reader-error" class="position-absolute top-50 start-50 translate-middle text-center d-none" style="z-index: 20;">
                <div class="alert alert-danger mb-0 shadow">
                    <h6 class="alert-heading fw-bold">Không thể tải sách</h6>
                    <p class="mb-0 small">Tệp sách có thể bị hỏng hoặc không đúng chuẩn EPUB.</p>
                </div>
            </div>
        </div>

        <!-- Table of Contents Offcanvas -->
        <div class="offcanvas offcanvas-start" tabindex="-1" id="tocOffcanvas" aria-labelledby="tocOffcanvasLabel">
            <div class="offcanvas-header border-bottom">
                <h5 class="offcanvas-title h6 mb-0" id="tocOffcanvasLabel">Mục lục — {{ $book->title }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Đóng"></button>
            </div>
            <div class="offcanvas-body p-0">
                <div id="toc-empty" class="p-3 text-muted small text-center">Đang tải mục lục...</div>
                <div class="list-group list-group-flush" id="toc-list"></div>
            </div>
        </div>

        <!-- Saved bookmarks and annotations -->
        <div class="offcanvas offcanvas-end" tabindex="-1" id="readerMarksOffcanvas" aria-labelledby="readerMarksOffcanvasLabel">
            <div class="offcanvas-header border-bottom">
                <h5 class="offcanvas-title h6 mb-0" id="readerMarksOffcanvasLabel">Dấu trang và ghi chú</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Đóng"></button>
            </div>
            <div class="offcanvas-body">
                <h2 class="h6">Dấu trang</h2>
                <div id="bookmarks-list" class="list-group mb-4"></div>
                <h2 class="h6">Đoạn tô sáng</h2>
                <div id="annotations-list" class="list-group"></div>
            </div>
        </div>

        <!-- Bookmark label editor -->
        <div class="modal fade" id="bookmarkModal" tabindex="-1" aria-labelledby="bookmarkModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title h6" id="bookmarkModalLabel">Lưu dấu trang</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label fw-semibold" for="bookmark-label">Tên dấu trang</label>
                        <input id="bookmark-label" class="form-control" maxlength="255" required>
                        <div class="form-text">Tên gợi ý gồm chương, trang và tiến độ; bạn có thể sửa lại.</div>
                        <div id="bookmark-error" class="alert alert-danger py-2 px-3 small d-none mt-3 mb-0" role="alert"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Hủy</button>
                        <button type="button" id="btn-save-bookmark" class="btn btn-primary btn-sm">Lưu dấu trang</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Annotation editor -->
        <div class="modal fade" id="annotationModal" tabindex="-1" aria-labelledby="annotationModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title h6" id="annotationModalLabel">Tô sáng và ghi chú</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label fw-semibold" for="annotation-quote">Đoạn đã chọn</label>
                        <textarea id="annotation-quote" class="form-control mb-3" rows="3" readonly></textarea>
                        <label class="form-label fw-semibold" for="annotation-note">Ghi chú</label>
                        <textarea id="annotation-note" class="form-control mb-3" rows="3" maxlength="12000" placeholder="Thêm ghi chú (không bắt buộc)"></textarea>
                        <label class="form-label fw-semibold" for="annotation-color">Màu tô</label>
                        <select id="annotation-color" class="form-select">
                            <option value="yellow">Vàng</option>
                            <option value="pink">Hồng</option>
                            <option value="blue">Xanh dương</option>
                            <option value="green">Xanh lá</option>
                        </select>
                        <div id="annotation-error" class="alert alert-danger py-2 px-3 small d-none mt-3 mb-0" role="alert"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Hủy</button>
                        <button type="button" id="btn-save-annotation" class="btn btn-primary btn-sm">Lưu tô sáng</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Settings Modal -->
        <div class="modal fade" id="settingsModal" tabindex="-1" aria-labelledby="settingsModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title h6 mb-0" id="settingsModalLabel">Cài đặt đọc sách</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Font Size -->
                        <div class="mb-4">
                            <label for="setting-font-size" class="form-label d-flex justify-content-between">
                                <span class="fw-semibold">Cỡ chữ</span>
                                <span id="font-size-val" class="badge bg-primary">{{ $settings->font_size }}px</span>
                            </label>
                            <input type="range" class="form-range" id="setting-font-size" min="8" max="48" step="1" value="{{ $settings->font_size }}">
                            <div class="d-flex justify-content-between text-muted small">
                                <span>8px</span>
                                <span>24px</span>
                                <span>48px</span>
                            </div>
                        </div>

                        <!-- Font Family -->
                        <div class="mb-4">
                            <label for="setting-font-family" class="form-label fw-semibold">Kiểu chữ</label>
                            <select class="form-select" id="setting-font-family">
                                @php
                                    $fontList = [
                                        'Serif'           => 'Serif (Có chân)',
                                        'Sans-serif'      => 'Sans-serif (Không chân)',
                                        'Georgia'         => 'Georgia',
                                        'Times New Roman' => 'Times New Roman',
                                        'Arial'           => 'Arial',
                                    ];
                                @endphp
                                @foreach ($fontList as $val => $label)
                                    <option value="{{ $val }}" {{ $settings->font_family === $val ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div id="settings-save-alert" class="alert alert-success py-2 px-3 small d-none mb-0">
                            Đã lưu cài đặt đọc sách.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Đóng</button>
                        <button type="button" id="btn-save-settings" class="btn btn-primary btn-sm">Lưu cài đặt</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection

@section('scripts')
@if ($fileExists)
    <!-- Exact versions are pinned so the reader does not change unexpectedly. -->
    <script src="https://cdn.jsdelivr.net/npm/jszip@3.1.5/dist/jszip.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/epubjs@0.3.93/dist/epub.min.js"></script>

    <script>
    (function () {
        const bookId = {{ (int) $book->id }};
        const fileUrl = "{{ route('books.file', $book->id) }}";
        const initialLocation = @json($progress?->location);
        let savedBookmarks = @json($bookmarks);
        let savedAnnotations = @json($annotations);
        const canAnnotate = @json($canAnnotate);
        const bookmarkStoreUrl = @json(route('books.bookmarks.store', $book->id));
        const bookmarkDeleteUrlTemplate = @json(route('books.bookmarks.destroy', [$book->id, '__mark_id__']));
        const annotationStoreUrl = @json(route('books.annotations.store', $book->id));
        const annotationDeleteUrlTemplate = @json(route('books.annotations.destroy', [$book->id, '__mark_id__']));
        let currentFontSize = {{ (int) $settings->font_size }};
        let currentFontFamily = @json($settings->font_family);
        let currentTheme = @json($settings->theme);
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const sessionStartedAt = new Date().toISOString();
        const completedChaptersKey = "private-library:completed-chapters:" + {{ (int) Auth::id() }} + ":" + bookId;
        let pendingBookmarkLocation = null;
        let pendingAnnotation = null;

        if (typeof window.ePub !== "function") {
            document.getElementById("reader-loading").classList.add("d-none");
            document.getElementById("reader-error").classList.remove("d-none");
            document.querySelector("#reader-error p").textContent =
                "Không tải được thư viện đọc sách. Hãy kiểm tra kết nối internet rồi tải lại trang.";
            return;
        }

        // Initialize Epub.js
        const book = window.ePub(fileUrl);
        const rendition = book.renderTo("viewer", {
            width: "100%",
            height: "100%",
            flow: "paginated",
            manager: "default"
        });

        function applyAppearance(fontSize, fontFamily, theme) {
            rendition.themes.fontSize(fontSize + "px");
            rendition.themes.font(fontFamily);

            rendition.getContents().forEach(function (contents) {
                applyThemeToContents(contents, theme, fontFamily);
            });

            const wrapper = document.getElementById("reader-wrapper");
            const viewer = document.getElementById("viewer");
            if (theme === "dark") {
                wrapper.style.backgroundColor = "#1a1a1a";
                viewer.style.backgroundColor = "#1a1a1a";
            } else {
                wrapper.style.backgroundColor = "#ffffff";
                viewer.style.backgroundColor = "#ffffff";
            }
        }

        function applyThemeToContents(contents, theme, fontFamily = currentFontFamily) {
            const contentDocument = contents.document;
            let themeStyle = contentDocument.getElementById("private-library-reader-theme");

            if (!themeStyle) {
                themeStyle = contentDocument.createElement("style");
                themeStyle.id = "private-library-reader-theme";
                (contentDocument.head || contentDocument.documentElement).appendChild(themeStyle);
            }

            const allowedFonts = ["Serif", "Sans-serif", "Georgia", "Times New Roman", "Arial"];
            const safeFontFamily = allowedFonts.includes(fontFamily) ? fontFamily : "Serif";
            const fontRule = "html, body, body * { font-family: '" + safeFontFamily + "' !important; } ";

            if (theme === "dark") {
                themeStyle.textContent = fontRule + "html, body { background-color: #1a1a1a !important; color: #e0e0e0 !important; cursor: default !important; caret-color: transparent !important; } body, body * { color: #e0e0e0 !important; cursor: default !important; caret-color: transparent !important; } a, a *, button, [role='button'] { color: #8ab4f8 !important; cursor: pointer !important; } input:not([type='checkbox']):not([type='radio']):not([type='file']), textarea, [contenteditable='true'] { cursor: text !important; caret-color: auto !important; }";
            } else {
                themeStyle.textContent = fontRule + "html, body { background-color: #ffffff !important; color: #212529 !important; cursor: default !important; caret-color: transparent !important; } body, body * { color: #212529 !important; cursor: default !important; caret-color: transparent !important; } a, a *, button, [role='button'] { color: #0d6efd !important; cursor: pointer !important; } input:not([type='checkbox']):not([type='radio']):not([type='file']), textarea, [contenteditable='true'] { cursor: text !important; caret-color: auto !important; }";
            }
        }

        async function sendMarkRequest(url, method, payload) {
            const response = await fetch(url, {
                method: method,
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": csrfToken,
                    "Accept": "application/json"
                },
                body: payload ? JSON.stringify(payload) : undefined
            });
            const data = await response.json().catch(function () { return {}; });

            if (!response.ok) {
                const firstValidationError = data.errors ? Object.values(data.errors).flat()[0] : null;
                throw new Error(firstValidationError || data.message || "Không thể lưu thay đổi.");
            }

            return data;
        }

        function showMarkFeedback(message, isError) {
            const feedback = document.getElementById("reader-mark-feedback");
            feedback.textContent = message;
            feedback.classList.toggle("alert-danger", Boolean(isError));
            feedback.classList.toggle("alert-success", !isError);
            feedback.classList.remove("d-none");
            clearTimeout(feedback.hideTimer);
            feedback.hideTimer = setTimeout(function () {
                feedback.classList.add("d-none");
            }, 3500);
        }

        function updatePageBadge(location) {
            const badge = document.getElementById("reader-page-badge");
            const displayed = location && location.start ? location.start.displayed : null;

            if (!displayed || !displayed.page || !displayed.total) {
                badge.textContent = "Trang --/--";
                return;
            }

            badge.textContent = "Trang " + displayed.page + "/" + displayed.total;
        }

        function annotationHighlightStyles(color) {
            const colors = {
                yellow: "#facc15",
                pink: "#f472b6",
                blue: "#60a5fa",
                green: "#4ade80"
            };

            return {
                fill: colors[color] || colors.yellow,
                "fill-opacity": "0.4",
                "mix-blend-mode": "multiply"
            };
        }

        function applySavedAnnotation(annotation) {
            rendition.annotations.highlight(
                annotation.location,
                { id: annotation.id, color: annotation.color },
                undefined,
                "reader-annotation",
                annotationHighlightStyles(annotation.color)
            );
        }

        function suggestBookmarkLabel(location) {
            const displayed = location.start.displayed;
            const spineIndex = Number(location.start.index);
            const chapter = tocChapterEntries.find(function (item) {
                return item.spineIndex === spineIndex;
            });
            const progressBadge = document.getElementById("progress-percent-badge");
            const percent = progressBadge ? Number.parseFloat(progressBadge.textContent) : 0;
            const context = [];

            if (chapter) context.push(chapter.label);
            if (displayed && displayed.total > 0) {
                context.push("trang " + displayed.page + "/" + displayed.total);
            }
            if (!Number.isNaN(percent)) context.push(percent.toFixed(1) + "%");

            return ("Dấu trang " + (savedBookmarks.length + 1) + (context.length ? " — " + context.join(" · ") : ""))
                .slice(0, 255);
        }

        function renderSavedMarks() {
            const bookmarkList = document.getElementById("bookmarks-list");
            const annotationList = document.getElementById("annotations-list");
            bookmarkList.innerHTML = "";
            annotationList.innerHTML = "";

            function appendEmptyState(list, message) {
                const empty = document.createElement("div");
                empty.className = "list-group-item small text-muted";
                empty.textContent = message;
                list.appendChild(empty);
            }

            function appendMark(list, mark, type) {
                const row = document.createElement("div");
                row.className = "list-group-item";

                const text = document.createElement("div");
                text.className = "small fw-semibold mb-1";
                text.textContent = type === "bookmark" ? (mark.note || ("Dấu trang #" + mark.id)) : mark.quote;
                row.appendChild(text);

                if (mark.created_at) {
                    const createdAt = document.createElement("div");
                    createdAt.className = "small text-muted";
                    const parsedDate = new Date(String(mark.created_at).replace(" ", "T"));
                    createdAt.textContent = Number.isNaN(parsedDate.getTime())
                        ? String(mark.created_at)
                        : "Lưu lúc " + parsedDate.toLocaleString("vi-VN");
                    row.appendChild(createdAt);
                }

                if (type === "annotation" && mark.note) {
                    const note = document.createElement("div");
                    note.className = "small text-muted mb-2";
                    note.textContent = mark.note;
                    row.appendChild(note);
                }

                const actions = document.createElement("div");
                actions.className = "d-flex gap-2 mt-2";

                const jumpButton = document.createElement("button");
                jumpButton.type = "button";
                jumpButton.className = "btn btn-outline-primary btn-sm";
                jumpButton.textContent = "Đi tới";
                jumpButton.addEventListener("click", function () {
                    rendition.display(mark.location).catch(function (err) {
                        console.error("Lỗi mở vị trí đã lưu:", err);
                    });
                    const offcanvas = bootstrap.Offcanvas.getInstance(document.getElementById("readerMarksOffcanvas"));
                    if (offcanvas) offcanvas.hide();
                });

                const deleteButton = document.createElement("button");
                deleteButton.type = "button";
                deleteButton.className = "btn btn-outline-danger btn-sm";
                deleteButton.textContent = "Xóa";
                deleteButton.addEventListener("click", async function () {
                    const confirmed = await window.showAppConfirm(
                        type === "bookmark" ? "Xóa dấu trang này?" : "Xóa ghi chú và phần tô sáng này?",
                        { title: "Xác nhận xóa", acceptLabel: "Xóa", variant: "danger" }
                    );
                    if (!confirmed) return;

                    try {
                        const urlTemplate = type === "bookmark" ? bookmarkDeleteUrlTemplate : annotationDeleteUrlTemplate;
                        const url = urlTemplate.replace("__mark_id__", encodeURIComponent(mark.id));
                        await sendMarkRequest(url, "DELETE");

                        if (type === "bookmark") {
                            savedBookmarks = savedBookmarks.filter(function (item) { return item.id !== mark.id; });
                        } else {
                            rendition.annotations.remove(mark.location, "highlight");
                            savedAnnotations = savedAnnotations.filter(function (item) { return item.id !== mark.id; });
                        }

                        renderSavedMarks();
                        showMarkFeedback(type === "bookmark" ? "Đã xóa dấu trang." : "Đã xóa ghi chú và phần tô sáng.", false);
                    } catch (err) {
                        showMarkFeedback(err.message, true);
                    }
                });

                actions.append(jumpButton, deleteButton);
                row.appendChild(actions);
                list.appendChild(row);
            }

            if (savedBookmarks.length === 0) {
                appendEmptyState(bookmarkList, "Chưa có dấu trang.");
            } else {
                savedBookmarks.forEach(function (bookmark) { appendMark(bookmarkList, bookmark, "bookmark"); });
            }

            if (savedAnnotations.length === 0) {
                appendEmptyState(annotationList, "Chưa có đoạn tô sáng.");
            } else {
                savedAnnotations.forEach(function (annotation) { appendMark(annotationList, annotation, "annotation"); });
            }
        }

        let wheelDelta = 0;
        let wheelGestureHandled = false;
        let wheelGestureResetTimer = null;
        let navigationPending = false;

        function clearReaderSelection() {
            const pageSelection = document.getSelection();
            if (pageSelection) pageSelection.removeAllRanges();

            rendition.getContents().forEach(function (contents) {
                const selection = contents.document.getSelection();
                if (selection) selection.removeAllRanges();
            });
        }

        function navigatePage(direction) {
            if (navigationPending) return Promise.resolve();

            navigationPending = true;
            hideReaderTip();

            const focusedElement = document.activeElement;
            if (focusedElement instanceof HTMLElement
                && ["btn-prev", "btn-next", "floating-prev", "floating-next"].includes(focusedElement.id)) {
                focusedElement.blur();
            }

            const navigation = direction === "next" ? rendition.next() : rendition.prev();

            return navigation.catch(function (err) {
                console.error("Lỗi chuyển trang:", err);
            }).finally(function () {
                clearReaderSelection();
                navigationPending = false;
            });
        }

        function handleReaderKeydown(event) {
            if (!["ArrowLeft", "ArrowRight"].includes(event.key)
                || event.repeat
                || event.ctrlKey
                || event.altKey
                || event.metaKey
                || (event.target instanceof HTMLElement && event.target.matches("input, textarea, select, [contenteditable='true']"))
                || document.querySelector(".offcanvas.show, .modal.show")) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            navigatePage(event.key === "ArrowRight" ? "next" : "previous");
        }

        rendition.hooks.content.register(function (contents) {
            applyThemeToContents(contents, currentTheme, currentFontFamily);

            contents.document.addEventListener("wheel", function (event) {
                if (event.ctrlKey || Math.abs(event.deltaY) < 1) return;

                event.preventDefault();

                clearTimeout(wheelGestureResetTimer);
                wheelGestureResetTimer = setTimeout(function () {
                    wheelGestureHandled = false;
                    wheelDelta = 0;
                }, 250);

                if (wheelGestureHandled || navigationPending) return;

                const deltaMultiplier = event.deltaMode === 1
                    ? 16
                    : event.deltaMode === 2
                        ? contents.document.documentElement.clientHeight
                        : 1;

                wheelDelta += event.deltaY * deltaMultiplier;
                if (Math.abs(wheelDelta) < 60) return;

                wheelGestureHandled = true;
                const direction = wheelDelta > 0 ? "next" : "previous";
                wheelDelta = 0;
                navigatePage(direction);
            }, { passive: false });

            contents.document.addEventListener("keydown", handleReaderKeydown);
        });

        rendition.on("selected", function (cfiRange, contents) {
            const selection = contents.window.getSelection();
            const quote = selection ? selection.toString().trim() : "";
            if (!quote) return;

            if (!canAnnotate) {
                showMarkFeedback("Chủ sách chỉ cấp quyền đọc, bạn không thể tạo ghi chú.", true);
                if (selection) selection.removeAllRanges();
                return;
            }

            pendingAnnotation = { location: cfiRange, quote: quote };
            document.getElementById("annotation-quote").value = quote;
            document.getElementById("annotation-note").value = "";
            document.getElementById("annotation-color").value = "yellow";
            document.getElementById("annotation-error").classList.add("d-none");
            bootstrap.Modal.getOrCreateInstance(document.getElementById("annotationModal")).show();
        });

        // Display book
        rendition.display(initialLocation || undefined).then(function () {
            const spinner = document.getElementById("reader-loading");
            if (spinner) spinner.classList.add("d-none");
            applyAppearance(currentFontSize, currentFontFamily, currentTheme);
            renderSavedMarks();
            savedAnnotations.forEach(applySavedAnnotation);

            const initialDisplayedLocation = rendition.currentLocation();
            updatePageBadge(initialDisplayedLocation);
            if (initialDisplayedLocation && initialDisplayedLocation.start) {
                updateCompletedChapters(initialDisplayedLocation);
            }

            book.ready.then(function () {
                book.locations.pause = 0;
                return book.locations.generate(5000);
            }).then(function () {
                const currentLocation = rendition.currentLocation();
                if (currentLocation && currentLocation.start) {
                    handleRelocated(currentLocation);
                }
            }).catch(function (err) {
                console.error("Lỗi khi tính tiến độ sách:", err);
            });
        }).catch(function (err) {
            console.error("Lỗi khi hiển thị sách:", err);
            const spinner = document.getElementById("reader-loading");
            if (spinner) spinner.classList.add("d-none");
            const errorEl = document.getElementById("reader-error");
            if (errorEl) errorEl.classList.remove("d-none");
        });

        // Navigation controls
        function hideReaderTip() {
            const readerTip = document.getElementById("reader-tip");
            if (readerTip) readerTip.classList.add("d-none");
        }

        function goPrev() { navigatePage("previous"); }
        function goNext() { navigatePage("next"); }

        document.getElementById("btn-prev").addEventListener("click", goPrev);
        document.getElementById("btn-next").addEventListener("click", goNext);
        document.getElementById("floating-prev").addEventListener("click", goPrev);
        document.getElementById("floating-next").addEventListener("click", goNext);

        // Keyboard navigation from the outer page; EPUB iframes use the same handler above.
        document.addEventListener("keydown", handleReaderKeydown);

        const bookmarkButton = document.getElementById("btn-bookmark-current");
        const bookmarkLabelInput = document.getElementById("bookmark-label");
        const bookmarkModalElement = document.getElementById("bookmarkModal");
        bookmarkButton.addEventListener("click", function () {
            const location = rendition.currentLocation();
            if (!location || !location.start || !location.start.cfi) {
                showMarkFeedback("Chưa xác định được vị trí hiện tại để lưu.", true);
                return;
            }

            pendingBookmarkLocation = location.start.cfi;
            bookmarkLabelInput.value = suggestBookmarkLabel(location);
            document.getElementById("bookmark-error").classList.add("d-none");
            bootstrap.Modal.getOrCreateInstance(bookmarkModalElement).show();
        });

        document.getElementById("btn-save-bookmark").addEventListener("click", async function () {
            const bookmarkError = document.getElementById("bookmark-error");
            const label = bookmarkLabelInput.value.trim();
            bookmarkError.classList.add("d-none");

            if (!pendingBookmarkLocation || !label) {
                bookmarkError.textContent = "Vui lòng nhập tên dấu trang.";
                bookmarkError.classList.remove("d-none");
                return;
            }

            const saveButton = document.getElementById("btn-save-bookmark");
            saveButton.disabled = true;
            try {
                const result = await sendMarkRequest(bookmarkStoreUrl, "POST", {
                    location: pendingBookmarkLocation,
                    note: label
                });
                savedBookmarks.unshift(result.bookmark);
                renderSavedMarks();
                pendingBookmarkLocation = null;
                bootstrap.Modal.getInstance(bookmarkModalElement).hide();
                showMarkFeedback("Đã lưu dấu trang có tên riêng.", false);
            } catch (err) {
                bookmarkError.textContent = err.message;
                bookmarkError.classList.remove("d-none");
            } finally {
                saveButton.disabled = false;
            }
        });

        bookmarkModalElement.addEventListener("hidden.bs.modal", function () {
            pendingBookmarkLocation = null;
        });

        const annotationSaveButton = document.getElementById("btn-save-annotation");
        annotationSaveButton.addEventListener("click", async function () {
            if (!pendingAnnotation) return;

            annotationSaveButton.disabled = true;
            const annotationError = document.getElementById("annotation-error");
            annotationError.classList.add("d-none");

            try {
                const result = await sendMarkRequest(annotationStoreUrl, "POST", {
                    location: pendingAnnotation.location,
                    quote: pendingAnnotation.quote,
                    note: document.getElementById("annotation-note").value.trim() || null,
                    color: document.getElementById("annotation-color").value
                });

                savedAnnotations.unshift(result.annotation);
                applySavedAnnotation(result.annotation);
                renderSavedMarks();
                pendingAnnotation = null;
                bootstrap.Modal.getInstance(document.getElementById("annotationModal")).hide();
                rendition.getContents().forEach(function (contents) {
                    const selection = contents.window.getSelection();
                    if (selection) selection.removeAllRanges();
                });
                showMarkFeedback("Đã lưu phần tô sáng và ghi chú.", false);
            } catch (err) {
                annotationError.textContent = err.message;
                annotationError.classList.remove("d-none");
            } finally {
                annotationSaveButton.disabled = false;
            }
        });

        document.getElementById("annotationModal").addEventListener("hidden.bs.modal", function () {
            pendingAnnotation = null;
        });

        // Table of contents
        let tocChapterEntries = [];
        let completedChapterHrefs = new Set();
        const chapterRowsByHref = new Map();
        const chapterLinksByHref = new Map();
        let previousSpineIndex = null;
        let skipChapterCompletionForNextRelocation = false;

        function saveCompletedChapters() {
            try {
                localStorage.setItem(completedChaptersKey, JSON.stringify(Array.from(completedChapterHrefs)));
            } catch (err) {
                console.warn("Không thể lưu danh sách chương đã hoàn thành:", err);
            }
        }

        function markSpineSectionComplete(spineIndex) {
            let hasChanges = false;

            tocChapterEntries.forEach(function (chapter) {
                if (chapter.spineIndex !== spineIndex || completedChapterHrefs.has(chapter.href)) return;

                completedChapterHrefs.add(chapter.href);
                const row = chapterRowsByHref.get(chapter.href);
                const link = chapterLinksByHref.get(chapter.href);
                if (row) row.classList.add("bg-success", "bg-opacity-10");
                if (link) link.classList.add("text-success", "fw-semibold");
                hasChanges = true;
            });

            if (hasChanges) saveCompletedChapters();
        }

        function updateCompletedChapters(location) {
            if (!location || !location.start || !Number.isInteger(Number(location.start.index))) return;

            const currentSpineIndex = Number(location.start.index);

            if (skipChapterCompletionForNextRelocation) {
                skipChapterCompletionForNextRelocation = false;
                previousSpineIndex = currentSpineIndex;
                return;
            }

            if (previousSpineIndex !== null && currentSpineIndex === previousSpineIndex + 1) {
                markSpineSectionComplete(previousSpineIndex);
            }

            const displayedEnd = location.end && location.end.displayed;
            const hasReachedSectionEnd = displayedEnd
                && displayedEnd.total > 0
                && displayedEnd.page >= displayedEnd.total;

            if (hasReachedSectionEnd || location.atEnd) {
                markSpineSectionComplete(currentSpineIndex);
            }

            previousSpineIndex = currentSpineIndex;
        }

        book.loaded.navigation.then(function (nav) {
            const tocList = document.getElementById("toc-list");
            const tocEmpty = document.getElementById("toc-empty");
            if (!nav.toc || nav.toc.length === 0) {
                tocEmpty.textContent = "Sách không có mục lục.";
                return;
            }
            tocEmpty.classList.add("d-none");
            tocList.innerHTML = "";

            try {
                const savedChapters = JSON.parse(localStorage.getItem(completedChaptersKey) || "[]");
                if (Array.isArray(savedChapters)) {
                    completedChapterHrefs = new Set(savedChapters);
                }
            } catch (err) {
                console.warn("Không thể đọc danh sách chương đã hoàn thành:", err);
            }

            function renderTocItems(items, depth) {
                items.forEach(function (item) {
                    const label = item.label ? item.label.trim() : "Chương";
                    const chapterSection = book.spine.get(item.href.split("#")[0]);
                    if (chapterSection && Number.isInteger(chapterSection.index)) {
                        tocChapterEntries.push({ href: item.href, spineIndex: chapterSection.index, label: label });
                    }

                    const row = document.createElement("div");
                    row.className = "list-group-item py-2 d-flex align-items-start gap-2";
                    row.style.paddingLeft = (12 + depth * 16) + "px";
                    chapterRowsByHref.set(item.href, row);

                    const a = document.createElement("a");
                    a.href = "#";
                    a.className = "flex-grow-1 text-decoration-none";
                    a.textContent = label;
                    chapterLinksByHref.set(item.href, a);
                    if (completedChapterHrefs.has(item.href)) {
                        row.classList.add("bg-success", "bg-opacity-10");
                        a.classList.add("text-success", "fw-semibold");
                    }
                    a.addEventListener("click", function (ev) {
                        ev.preventDefault();
                        skipChapterCompletionForNextRelocation = true;
                        const previousIndex = previousSpineIndex;
                        rendition.display(item.href).then(function () {
                            if (previousSpineIndex === previousIndex) {
                                skipChapterCompletionForNextRelocation = false;
                            }
                        }).catch(function (err) {
                            skipChapterCompletionForNextRelocation = false;
                            console.error("Lỗi mở chương từ mục lục:", err);
                        });
                        const offcanvasEl = document.getElementById("tocOffcanvas");
                        const offcanvas = bootstrap.Offcanvas.getInstance(offcanvasEl);
                        if (offcanvas) offcanvas.hide();
                    });
                    row.append(a);
                    tocList.appendChild(row);
                    if (item.subitems && item.subitems.length > 0) {
                        renderTocItems(item.subitems, depth + 1);
                    }
                });
            }
            renderTocItems(nav.toc, 0);
        });

        // Position memory & immediate save after each page relocation
        let lastSavedCfi = initialLocation;
        let lastSavedPercent = {{ $progress && $progress->percent !== null ? (float) $progress->percent : 0 }};

        function handleRelocated(location) {
            if (!location || !location.start) return;
            updatePageBadge(location);
            updateCompletedChapters(location);
            const cfi = location.start.cfi;
            let percent = 0;

            if (book.locations && book.locations.length() > 0) {
                const p = book.locations.percentageFromCfi(cfi);
                if (typeof p === "number" && !isNaN(p)) {
                    percent = Math.min(100, Math.max(0, +(p * 100).toFixed(2)));
                }
            } else {
                const linearSections = book.spine.spineItems.filter(function (section) {
                    return section.linear;
                });
                const sectionPosition = linearSections.findIndex(function (section) {
                    return section.index === Number(location.start.index);
                });
                const displayed = location.start.displayed;

                if (sectionPosition >= 0 && linearSections.length > 0 && displayed && displayed.total > 0) {
                    percent = ((sectionPosition + (displayed.page / displayed.total)) / linearSections.length) * 100;
                    percent = Math.min(100, Math.max(0, +percent.toFixed(2)));
                } else if (lastSavedPercent > 0) {
                    percent = lastSavedPercent;
                }
            }

            lastSavedCfi = cfi;
            percent = Math.max(lastSavedPercent, percent);
            lastSavedPercent = percent;

            // Update UI badge
            const badge = document.getElementById("progress-percent-badge");
            if (badge) {
                badge.textContent = percent.toFixed(1) + "%";
            }

            saveProgress(cfi, percent, false);
        }

        rendition.on("relocated", handleRelocated);

        function saveProgress(cfi, percent, isSync) {
            const url = "{{ route('books.progress', $book->id) }}";

            if (isSync && navigator.sendBeacon) {
                const fd = new FormData();
                fd.append("_token", csrfToken);
                fd.append("location", cfi);
                fd.append("percent", percent);
                navigator.sendBeacon(url, fd);
                return;
            }

            progressSaveQueue = progressSaveQueue.catch(function () {})
                .then(function () {
                    return fetch(url, {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            "X-CSRF-TOKEN": csrfToken,
                            "Accept": "application/json"
                        },
                        body: JSON.stringify({
                            location: cfi,
                            percent: percent
                        }),
                        keepalive: isSync
                    });
                })
                .catch(function (err) {
                    console.error("Lỗi lưu tiến độ:", err);
                });
        }

        let progressSaveQueue = Promise.resolve();

        // Sessions tracking on page leave
        let sessionRecorded = false;

        function recordReadingSession() {
            if (sessionRecorded) return;
            sessionRecorded = true;

            if (lastSavedCfi) {
                saveProgress(lastSavedCfi, lastSavedPercent, true);
            }

            const url = "{{ route('books.sessions', $book->id) }}";
            const endedAt = new Date().toISOString();

            if (navigator.sendBeacon) {
                const fd = new FormData();
                fd.append("_token", csrfToken);
                fd.append("started_at", sessionStartedAt);
                fd.append("ended_at", endedAt);
                navigator.sendBeacon(url, fd);
            } else {
                fetch(url, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": csrfToken,
                        "Accept": "application/json"
                    },
                    body: JSON.stringify({
                        started_at: sessionStartedAt,
                        ended_at: endedAt
                    }),
                    keepalive: true
                }).catch(function (err) {
                    console.error("Lỗi ghi nhận phiên đọc:", err);
                });
            }
        }

        window.addEventListener("pagehide", recordReadingSession);
        window.addEventListener("beforeunload", recordReadingSession);

        // Settings modal live preview and save
        const fontSizeInput = document.getElementById("setting-font-size");
        const fontSizeVal = document.getElementById("font-size-val");
        fontSizeInput.addEventListener("input", function (e) {
            fontSizeVal.textContent = e.target.value + "px";
            applyAppearance(+e.target.value, currentFontFamily, currentTheme);
        });

        const fontFamilySelect = document.getElementById("setting-font-family");
        fontFamilySelect.addEventListener("change", function (e) {
            currentFontFamily = e.target.value;
            applyAppearance(+fontSizeInput.value, currentFontFamily, currentTheme);
        });

        document.addEventListener("private-library-theme-changed", function (event) {
            currentTheme = event.detail.theme;
            applyAppearance(+fontSizeInput.value, currentFontFamily, currentTheme);
        });

        function saveSettingsToBackend() {
            const newSize = +fontSizeInput.value;
            const newFamily = fontFamilySelect.value;

            currentFontSize = newSize;
            currentFontFamily = newFamily;

            fetch("{{ route('reading-settings.update') }}", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": csrfToken,
                    "Accept": "application/json"
                },
                body: JSON.stringify({
                    font_size: newSize,
                    font_family: newFamily
                })
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                const alertEl = document.getElementById("settings-save-alert");
                if (alertEl) {
                    alertEl.classList.remove("d-none");
                    setTimeout(function () {
                        alertEl.classList.add("d-none");
                    }, 2500);
                }
            })
            .catch(function (err) {
                console.error("Lỗi lưu cài đặt:", err);
            });
        }

        document.getElementById("btn-save-settings").addEventListener("click", saveSettingsToBackend);
    })();
    </script>
@endif
@endsection
