@if ($uploads->isNotEmpty())
    <section class="book-upload-status-list mb-4" data-book-uploads data-status-url="{{ route('book-uploads.status') }}" aria-label="Tiến trình tải sách">
        @foreach ($uploads as $upload)
            @php
                $uploadMessages = [
                    'pending' => 'Đang chờ xử lý…',
                    'processing' => 'Tác vụ đang chạy nền.',
                    'completed' => $upload->is_public ? 'Đã xong, sách đã được thêm vào kho công khai.' : 'Đã xong, sách đã được thêm vào thư viện.',
                    'failed' => $upload->error_message ?: 'Không xử lý được tệp sách. Hãy thử tải lại.',
                    'cancelled' => 'Tác vụ đã bị hủy.',
                ];
            @endphp
            <div class="alert @if ($upload->status === 'failed') alert-danger @elseif ($upload->status === 'completed') alert-success @elseif ($upload->status === 'cancelled') alert-secondary @else alert-info @endif py-2 mb-2" data-book-upload data-upload-id="{{ $upload->id }}" data-status="{{ $upload->status }}" data-public="{{ (bool) $upload->is_public ? 'true' : 'false' }}" data-format="{{ $upload->source_format }}" role="status">
                <strong>{{ $upload->title }}</strong>
                <span data-upload-message>{{ $uploadMessages[$upload->status] ?? 'Đang xử lý…' }}</span>
                @if (in_array($upload->status, ['pending', 'processing'], true))
                    <button type="button" class="btn btn-sm btn-outline-danger float-end ms-2" data-cancel-upload data-cancel-url="{{ route('book-uploads.cancel', $upload->id) }}" data-dismiss-url="{{ route('book-uploads.destroy', $upload->id) }}">Hủy</button>
                @elseif (in_array($upload->status, ['completed', 'failed', 'cancelled'], true))
                    <button type="button" class="btn-close float-end ms-2" data-dismiss-upload data-dismiss-url="{{ route('book-uploads.destroy', $upload->id) }}" aria-label="Xóa thông báo {{ $upload->title }}"></button>
                @endif
                @if (! in_array($upload->status, ['failed', 'cancelled'], true))
                    <div class="mt-2" data-upload-progress-container>
                        <div class="progress" role="progressbar" aria-label="Tiến độ xử lý {{ $upload->title }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ (int) $upload->progress_percent }}">
                            <div class="progress-bar @if (in_array($upload->status, ['pending', 'processing'], true)) progress-bar-striped progress-bar-animated @endif" data-upload-progress-bar style="width: {{ (int) $upload->progress_percent }}%"></div>
                        </div>
                        <small class="d-block mt-1" data-upload-progress-message>{{ $upload->progress_message ?: 'Đang chờ xử lý…' }} · {{ (int) $upload->progress_percent }}%</small>
                    </div>
                @endif
            </div>
        @endforeach
    </section>
    <script>
        (() => {
            const container = document.querySelector('[data-book-uploads]');
            if (!container) return;

            let reloadAfterCompletion = false;
            let pollingInterval;
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            const hasActiveUploads = () => [...container.querySelectorAll('[data-book-upload]')]
                .some((item) => ['pending', 'processing'].includes(item.dataset.status));

            container.addEventListener('click', async (event) => {
                const cancelButton = event.target.closest('[data-cancel-upload]');
                if (cancelButton) {
                    cancelButton.disabled = true;
                    cancelButton.textContent = 'Đang hủy…';
                    try {
                        const response = await fetch(cancelButton.dataset.cancelUrl, {
                            method: 'POST',
                            headers: {
                                Accept: 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            credentials: 'same-origin',
                        });
                        if (!response.ok) throw new Error('Cancel request failed.');

                        await refreshUploads();
                    } catch {
                        cancelButton.disabled = false;
                        cancelButton.textContent = 'Hủy';
                        cancelButton.title = 'Không hủy được tác vụ. Hãy thử lại.';
                    }
                    return;
                }

                const button = event.target.closest('[data-dismiss-upload]');
                if (!button) return;

                const item = button.closest('[data-book-upload]');
                button.disabled = true;
                try {
                    const response = await fetch(button.dataset.dismissUrl, {
                        method: 'DELETE',
                        headers: {
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        credentials: 'same-origin',
                    });
                    if (!response.ok) throw new Error('Dismiss request failed.');

                    item.remove();
                    if (!container.querySelector('[data-book-upload]')) container.remove();
                } catch {
                    button.disabled = false;
                    button.setAttribute('aria-label', 'Không xóa được thông báo. Hãy thử lại.');
                }
            });

            const refreshUploads = async () => {
                try {
                    const response = await fetch(container.dataset.statusUrl, {
                        headers: { Accept: 'application/json' },
                        credentials: 'same-origin',
                    });
                    if (!response.ok) return;

                    const result = await response.json();
                    for (const upload of result.uploads) {
                        const item = container.querySelector(`[data-upload-id="${upload.id}"]`);
                        if (!item) continue;

                        const statusChanged = item.dataset.status !== upload.status;
                        item.dataset.status = upload.status;
                        const message = item.querySelector('[data-upload-message]');
                        const progressContainer = item.querySelector('[data-upload-progress-container]');
                        if (upload.status === 'processing') {
                            if (statusChanged) item.className = 'alert alert-info py-2 mb-2';
                            message.textContent = 'Tác vụ đang chạy nền.';
                        } else if (upload.status === 'pending') {
                            if (statusChanged) item.className = 'alert alert-info py-2 mb-2';
                            message.textContent = upload.progress_message || 'Đang chờ xử lý…';
                        } else if (upload.status === 'completed') {
                            if (statusChanged) item.className = 'alert alert-success py-2 mb-2';
                            message.textContent = item.dataset.public === 'true'
                                ? 'Đã xong, sách đã được thêm vào kho công khai.'
                                : 'Đã xong, sách đã được thêm vào thư viện.';
                            reloadAfterCompletion = true;
                        } else if (upload.status === 'failed') {
                            if (statusChanged) item.className = 'alert alert-danger py-2 mb-2';
                            message.textContent = upload.error_message || 'Không xử lý được tệp sách. Hãy thử tải lại.';
                            if (progressContainer) progressContainer.remove();
                        } else if (upload.status === 'cancelled') {
                            if (statusChanged) item.className = 'alert alert-secondary py-2 mb-2';
                            message.textContent = 'Tác vụ đã bị hủy.';
                            if (progressContainer) progressContainer.remove();
                            const cancelButton = item.querySelector('[data-cancel-upload]');
                            if (cancelButton) {
                                cancelButton.removeAttribute('data-cancel-upload');
                                cancelButton.setAttribute('data-dismiss-upload', '');
                                cancelButton.className = 'btn-close float-end ms-2';
                                cancelButton.textContent = '';
                                cancelButton.disabled = false;
                                cancelButton.setAttribute('aria-label', `Xóa thông báo ${upload.title}`);
                            }
                        }

                        if (progressContainer && upload.status !== 'failed') {
                            const percent = Number(upload.progress_percent) || 0;
                            const progressBar = progressContainer.querySelector('[data-upload-progress-bar]');
                            const progressMessage = progressContainer.querySelector('[data-upload-progress-message]');
                            const progressBarContainer = progressContainer.querySelector('[role="progressbar"]');
                            progressBar.style.width = `${Math.max(0, Math.min(100, percent))}%`;
                            progressBar.classList.toggle('progress-bar-striped', ['pending', 'processing'].includes(upload.status));
                            progressBar.classList.toggle('progress-bar-animated', ['pending', 'processing'].includes(upload.status));
                            progressBarContainer.setAttribute('aria-valuenow', String(percent));
                            progressMessage.textContent = `${upload.progress_message || 'Đang xử lý sách…'} · ${percent}%`;
                        }
                    }

                    if (reloadAfterCompletion) {
                        window.setTimeout(() => window.location.reload(), 2200);
                    } else if (!hasActiveUploads()) {
                        window.clearInterval(pollingInterval);
                    }
                } catch {
                    window.clearInterval(pollingInterval);
                }
            };

            if (hasActiveUploads()) {
                pollingInterval = window.setInterval(refreshUploads, 2500);
                refreshUploads();
            }
        })();
    </script>
@endif
