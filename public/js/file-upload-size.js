document.querySelectorAll("input[type='file'][data-file-size]").forEach((input) => {
    const output = document.getElementById(input.dataset.fileSizeTarget);
    if (!output) return;

    input.addEventListener('change', () => {
        const file = input.files?.[0];
        output.textContent = file
            ? `${file.name} · ${(file.size / 1048576).toLocaleString('vi-VN', { maximumFractionDigits: 2 })} MB`
            : 'Chưa chọn tệp.';
    });
});

document.querySelectorAll('form[data-async-book-upload]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        event.preventDefault();

        const progress = form.querySelector('[data-upload-progress]');
        const progressBar = form.querySelector('[data-upload-progress-bar]');
        const progressMessage = form.querySelector('[data-upload-progress-message]');
        const errorRegion = form.querySelector('[data-upload-errors]');
        const submitButtons = [...form.querySelectorAll("button[type='submit'], button:not([type])")];
        const originalButtonLabels = submitButtons.map((button) => button.textContent);
        const xhr = new XMLHttpRequest();

        const showError = (messages) => {
            errorRegion.replaceChildren();
            const list = document.createElement('ul');
            list.className = 'mb-0';
            messages.forEach((message) => {
                const item = document.createElement('li');
                item.textContent = message;
                list.append(item);
            });
            errorRegion.append(list);
            errorRegion.hidden = false;
            progress.hidden = true;
            submitButtons.forEach((button, index) => {
                button.disabled = false;
                button.textContent = originalButtonLabels[index];
            });
        };

        errorRegion.hidden = true;
        errorRegion.replaceChildren();
        progress.hidden = false;
        progressBar.style.width = '0%';
        progress.setAttribute('aria-valuenow', '0');
        progressMessage.textContent = 'Đang chuẩn bị tải tệp…';
        submitButtons.forEach((button) => {
            button.disabled = true;
            button.textContent = 'Đang tải lên…';
        });

        xhr.open('POST', form.action);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        xhr.upload.addEventListener('progress', (progressEvent) => {
            if (!progressEvent.lengthComputable) {
                progressMessage.textContent = 'Đang tải tệp lên máy chủ…';
                return;
            }

            const percent = Math.min(100, Math.round((progressEvent.loaded / progressEvent.total) * 100));
            progressBar.style.width = `${percent}%`;
            progress.setAttribute('aria-valuenow', String(percent));
            progressMessage.textContent = `Đang tải tệp lên máy chủ: ${percent}%`;
        });

        xhr.upload.addEventListener('load', () => {
            progressBar.style.width = '100%';
            progress.setAttribute('aria-valuenow', '100');
            progressMessage.textContent = 'Đã gửi xong tệp, đang tạo tác vụ xử lý…';
        });

        xhr.addEventListener('load', () => {
            let response = {};
            try {
                response = JSON.parse(xhr.responseText);
            } catch {
                // Handle unexpected server responses below.
            }

            if (xhr.status >= 200 && xhr.status < 300 && typeof response.redirect === 'string') {
                window.location.assign(response.redirect);
                return;
            }

            if (xhr.status === 422 && response.errors) {
                showError(Object.values(response.errors).flat());
                return;
            }

            showError([response.message || 'Không thể tải sách lên. Vui lòng thử lại.']);
        });

        xhr.addEventListener('error', () => showError(['Mất kết nối khi tải tệp lên. Hãy kiểm tra mạng rồi thử lại.']));
        xhr.addEventListener('abort', () => showError(['Đã hủy tải tệp lên.']));
        xhr.send(new FormData(form));
    });
});
