import './echo';

const confirmModalElement = document.getElementById('appConfirmModal');
const confirmModal = confirmModalElement && window.bootstrap?.Modal
    ? window.bootstrap.Modal.getOrCreateInstance(confirmModalElement)
    : null;
const confirmTitle = confirmModalElement?.querySelector('#appConfirmTitle');
const confirmMessage = confirmModalElement?.querySelector('#appConfirmMessage');
const confirmAccept = confirmModalElement?.querySelector('[data-app-confirm-accept]');
const confirmCancel = confirmModalElement?.querySelector('[data-app-confirm-cancel]');
let resolveConfirmation = null;

window.showAppConfirm = (message, options = {}) => {
    if (!confirmModal || !confirmTitle || !confirmMessage || !confirmAccept) {
        return Promise.resolve(false);
    }

    if (resolveConfirmation) {
        resolveConfirmation(false);
    }

    confirmTitle.textContent = options.title || 'Xác nhận thao tác';
    confirmMessage.textContent = message;
    confirmAccept.textContent = options.acceptLabel || 'Tiếp tục';
    confirmAccept.classList.toggle('btn-danger', options.variant === 'danger');
    confirmAccept.classList.toggle('btn-primary', options.variant !== 'danger');
    confirmModalElement.classList.toggle('is-danger', options.variant === 'danger');

    return new Promise((resolve) => {
        resolveConfirmation = resolve;
        confirmModal.show();
    });
};

const finishConfirmation = (confirmed) => {
    if (!resolveConfirmation) {
        return;
    }

    const resolve = resolveConfirmation;
    resolveConfirmation = null;
    resolve(confirmed);
};

confirmAccept?.addEventListener('click', () => {
    finishConfirmation(true);
    confirmModal?.hide();
});
confirmCancel?.addEventListener('click', () => {
    finishConfirmation(false);
    confirmModal?.hide();
});
confirmModalElement?.addEventListener('hidden.bs.modal', () => finishConfirmation(false));

document.addEventListener('submit', async (event) => {
    const form = event.target.closest('form[data-confirm-message]');
    if (!form) {
        return;
    }

    if (form.dataset.confirmed === 'true') {
        delete form.dataset.confirmed;
        return;
    }

    event.preventDefault();
    const submitter = event.submitter;
    const confirmed = await window.showAppConfirm(form.dataset.confirmMessage, {
        title: form.dataset.confirmTitle,
        acceptLabel: form.dataset.confirmAccept,
        variant: form.dataset.confirmVariant,
    });

    if (confirmed && form.isConnected) {
        form.dataset.confirmed = 'true';
        if (submitter && form.contains(submitter)) {
            form.requestSubmit(submitter);
        } else {
            form.requestSubmit();
        }
    }
}, true);

const notificationConfig = window.privateLibraryNotificationConfig;

if (notificationConfig) {
    const counters = [...document.querySelectorAll('[data-notification-count]')];
    let unreadCount = Number.parseInt(counters[0]?.textContent.trim() || '0', 10) || 0;
    const seenNotificationIds = new Set();
    let notificationPollInitialized = false;
    const initialNotificationIds = new Set([...document.querySelectorAll('[data-notification-id]')]
        .map((card) => card.dataset.notificationId));
    initialNotificationIds.forEach((id) => seenNotificationIds.add(id));

    const makeHiddenInput = (name, value) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        return input;
    };

    const notificationForm = (action, method, csrfToken) => {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = action;
        form.append(makeHiddenInput('_token', csrfToken));
        if (method !== 'POST') {
            form.append(makeHiddenInput('_method', method));
        }
        return form;
    };

    const addNotificationToFeed = (notification) => {
        const feed = document.querySelector('[data-notification-feed]');
        const list = feed?.querySelector('[data-notification-list]');
        if (!feed || !list || Number(feed.dataset.feedPage) !== 1 || !notification.id) {
            return;
        }

        const card = document.createElement('article');
        const isUnread = !notification.read_at;
        card.className = `notification-card${isUnread ? ' is-unread' : ''}`;
        card.dataset.notificationId = notification.id;
        card.dataset.createdAt = notification.created_at || new Date().toISOString();
        card.dataset.readUrl = notificationConfig.readUrlTemplate.replace('__notification_id__', encodeURIComponent(notification.id));
        if (isUnread) {
            card.tabIndex = 0;
        }

        const indicator = document.createElement('span');
        indicator.className = 'notification-indicator';
        indicator.setAttribute('aria-hidden', 'true');
        const copy = document.createElement('div');
        copy.className = 'notification-copy';
        const heading = document.createElement('h2');
        heading.textContent = notification.title || 'Cập nhật mới';
        const message = document.createElement('p');
        message.textContent = notification.message || '';
        copy.append(heading, message);

        if (notification.event === 'rejected' && notification.review_note) {
            const reviewNote = document.createElement('div');
            reviewNote.className = 'notification-review-note';
            const label = document.createElement('strong');
            label.textContent = 'Lý do từ chối: ';
            reviewNote.append(label, document.createTextNode(notification.review_note));
            copy.append(reviewNote);
        }

        const date = new Date(card.dataset.createdAt);
        const time = document.createElement('time');
        time.dateTime = date.toISOString();
        time.textContent = new Intl.DateTimeFormat('vi-VN', {
            day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
        }).format(date);
        copy.append(time);

        const actions = document.createElement('div');
        actions.className = 'notification-actions';
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        if (isUnread) {
            const readForm = notificationForm(
                notificationConfig.readUrlTemplate.replace('__notification_id__', encodeURIComponent(notification.id)),
                'POST', csrfToken,
            );
            readForm.dataset.notificationReadForm = '';
            const openButton = document.createElement('button');
            openButton.type = 'submit';
            openButton.className = 'btn btn-sm btn-outline-success';
            openButton.textContent = 'Mở thông báo';
            readForm.append(openButton);
            actions.append(readForm);
        } else {
            const readLabel = document.createElement('span');
            readLabel.className = 'notification-read-label';
            readLabel.textContent = 'Đã đọc';
            actions.append(readLabel);
        }

        const deleteForm = notificationForm(
            notificationConfig.deleteUrlTemplate.replace('__notification_id__', encodeURIComponent(notification.id)),
            'DELETE', csrfToken,
        );
        deleteForm.dataset.confirmMessage = 'Xóa thông báo này?';
        deleteForm.dataset.confirmTitle = 'Xóa thông báo?';
        deleteForm.dataset.confirmAccept = 'Xóa thông báo';
        deleteForm.dataset.confirmVariant = 'danger';
        const deleteButton = document.createElement('button');
        deleteButton.type = 'submit';
        deleteButton.className = 'btn btn-sm btn-outline-danger';
        deleteButton.textContent = 'Xóa';
        deleteForm.append(deleteButton);
        actions.append(deleteForm);

        card.append(indicator, copy, actions);
        list.querySelector('.notification-empty')?.remove();
        list.prepend(card);
        feed.dataset.latestTimestamp = card.dataset.createdAt;

        if (isUnread && !feed.querySelector('[data-notification-read-all]')) {
            const readAllForm = notificationForm(notificationConfig.readAllUrl, 'POST', csrfToken);
            const readAllButton = document.createElement('button');
            readAllButton.type = 'submit';
            readAllButton.className = 'btn btn-outline-secondary';
            readAllButton.dataset.notificationReadAll = '';
            readAllButton.textContent = 'Đánh dấu tất cả đã đọc';
            readAllForm.append(readAllButton);
            feed.querySelector('.notification-header')?.append(readAllForm);
        }

        [...list.querySelectorAll('[data-notification-id]')].slice(20).forEach((oldCard) => oldCard.remove());
    };

    const updateCounters = () => {
        counters.forEach((counter) => {
            counter.textContent = unreadCount > 99 ? '99+' : String(unreadCount);
            counter.hidden = unreadCount === 0;
        });
    };

    const showNotificationToast = (notification) => {
        let region = document.querySelector('[data-notification-toasts]');
        if (!region) {
            region = document.createElement('div');
            region.className = 'notification-toast-region';
            region.dataset.notificationToasts = '';
            region.setAttribute('role', 'region');
            region.setAttribute('aria-label', 'Thông báo mới');
            document.body.append(region);
        }
        const toast = document.createElement('article');
        toast.className = 'notification-toast';
        toast.setAttribute('role', 'status');

        const heading = document.createElement('strong');
        heading.textContent = notification.title || 'Thông báo mới';
        const message = document.createElement('p');
        message.textContent = notification.message || 'Bạn có một cập nhật mới.';
        if (notification.event === 'rejected' && notification.review_note) {
            const reviewNote = document.createElement('p');
            reviewNote.className = 'notification-toast-review-note';
            reviewNote.textContent = `Lý do từ chối: ${notification.review_note}`;
            toast.append(heading, message, reviewNote);
        } else {
            toast.append(heading, message);
        }
        const link = document.createElement('a');
        link.href = notificationConfig.notificationsUrl;
        link.textContent = 'Xem thông báo';
        const dismiss = document.createElement('button');
        dismiss.type = 'button';
        dismiss.className = 'notification-toast-dismiss';
        dismiss.setAttribute('aria-label', 'Đóng thông báo');
        dismiss.textContent = '×';
        dismiss.addEventListener('click', () => toast.remove());

        toast.append(link, dismiss);
        region.appendChild(toast);
        window.setTimeout(() => toast.remove(), 8000);
    };

    const receiveNotification = (notification, incrementUnread, showToast = true) => {
        if (notification.audience !== notificationConfig.audience) {
            return;
        }
        if (notification.id && seenNotificationIds.has(notification.id)) {
            return;
        }
        if (notification.id) {
            seenNotificationIds.add(notification.id);
        }

        if (incrementUnread) {
            unreadCount += 1;
            updateCounters();
        }
        if (notificationConfig.audience === 'user') {
            updateLibraryBookStatus(notification);
        }
        if (showToast) {
            showNotificationToast(notification);
        }
        addNotificationToFeed(notification);
    };

    const markNotificationCardRead = async (card) => {
        if (!card?.classList.contains('is-unread') || card.dataset.markingRead === 'true') {
            return;
        }

        const readForm = card.querySelector('[data-notification-read-form]');
        const actions = card.querySelector('.notification-actions');
        const readUrl = card.dataset.readUrl || readForm?.action;
        if (!readForm || !actions || !readUrl) {
            return;
        }

        card.dataset.markingRead = 'true';
        const previousUnreadCount = unreadCount;
        const readLabel = document.createElement('span');
        readLabel.className = 'notification-read-label';
        readLabel.textContent = 'Đã đọc';
        card.classList.remove('is-unread');
        card.removeAttribute('tabindex');
        readForm.remove();
        actions.prepend(readLabel);
        unreadCount = Math.max(0, unreadCount - 1);
        updateCounters();

        try {
            const response = await fetch(readUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
            });
            if (!response.ok || new URL(response.url).pathname === '/login') {
                throw new Error('Không thể cập nhật trạng thái thông báo.');
            }

            const result = await response.json().catch(() => null);
            if (Number.isInteger(result?.unread_count)) {
                unreadCount = Math.max(0, result.unread_count);
                updateCounters();
            }
        } catch {
            card.classList.add('is-unread');
            card.tabIndex = 0;
            readLabel.remove();
            actions.prepend(readForm);
            unreadCount = previousUnreadCount;
            updateCounters();
        } finally {
            delete card.dataset.markingRead;
        }
    };

    document.addEventListener('click', (event) => {
        const card = event.target.closest('[data-notification-id].notification-card');
        if (!card || event.target.closest('a, button, form, input, select, textarea')) {
            return;
        }

        markNotificationCardRead(card);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }

        const card = event.target.closest('[data-notification-id].notification-card[tabindex="0"]');
        if (card && !event.target.closest('a, button, form, input, select, textarea')) {
            event.preventDefault();
            markNotificationCardRead(card);
        }
    });

    const pollNotifications = async () => {
        try {
            const response = await fetch(notificationConfig.pollUrl, {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
            });
            if (!response.ok) {
                return;
            }

            const result = await response.json();
            unreadCount = Math.max(0, Number.parseInt(result.unread_count, 10) || 0);
            updateCounters();

            if (!notificationPollInitialized) {
                const currentFeed = document.querySelector('[data-notification-feed]');
                const canDetectNewFeedItems = currentFeed && Number(currentFeed.dataset.feedPage) === 1;
                const newestNotification = result.notifications?.[0];
                const newestRenderedAt = Date.parse(currentFeed?.dataset.latestTimestamp || '');
                const newestCreatedAt = Date.parse(newestNotification?.created_at || '');
                const foundNewFeedItem = canDetectNewFeedItems
                    && newestNotification?.id
                    && !seenNotificationIds.has(newestNotification.id)
                    && newestCreatedAt >= newestRenderedAt;

                if (foundNewFeedItem) {
                    receiveNotification(newestNotification, false, !newestNotification.read_at);
                }

                (result.notifications || []).forEach((notification) => {
                    if (notification.id) {
                        seenNotificationIds.add(notification.id);
                    }
                    if (notification.audience === 'user') {
                        updateLibraryBookStatus(notification);
                    }
                });
                notificationPollInitialized = true;
                return;
            }

            (result.notifications || []).reverse().forEach((notification) => {
                receiveNotification(notification, false, !notification.read_at);
            });
        } catch {
            // Keep the latest displayed count when polling is temporarily unavailable.
        }
    };

    const updateLibraryBookStatus = (notification) => {
        if (!notification.book_id || !['submitted', 'approved', 'rejected'].includes(notification.event)) {
            return;
        }

        const card = [...document.querySelectorAll('[data-book-card]')]
            .find((bookCard) => bookCard.dataset.bookId === String(notification.book_id));
        const statusBadge = card?.querySelector('[data-submission-status]');
        if (!card || !statusBadge) {
            return;
        }

        const statePresentation = {
            submitted: { state: 'pending', className: 'badge text-bg-warning', label: 'Đang chờ duyệt' },
            approved: { state: 'approved', className: 'badge text-bg-info', label: 'Sách công khai' },
            rejected: { state: 'rejected', className: 'badge text-bg-danger', label: 'Chưa được duyệt' },
        }[notification.event];

        statusBadge.hidden = false;
        statusBadge.className = statePresentation.className;
        statusBadge.dataset.submissionState = statePresentation.state;
        statusBadge.textContent = statePresentation.label;

        const submissionForm = card.querySelector('[data-public-submission-form]');
        const resubmitButton = submissionForm?.querySelector('[data-submission-button]');
        const reviewNote = card.querySelector('[data-submission-review-note]');

        if (submissionForm) {
            submissionForm.hidden = notification.event !== 'rejected';
        }
        if (resubmitButton && notification.event === 'rejected') {
            resubmitButton.textContent = 'Gửi lại để duyệt';
        }
        if (reviewNote) {
            reviewNote.textContent = notification.review_note ? `Lý do: ${notification.review_note}` : '';
            reviewNote.hidden = notification.event !== 'rejected' || !notification.review_note;
        }
    };

    const updateReviewQueueCount = (queue, totalCount = null) => {
        const visibleCount = queue.querySelectorAll('[data-review-book-id]').length;
        const count = totalCount ?? visibleCount;
        const countLabel = queue.querySelector('[data-review-count]');
        const list = queue.querySelector('[data-review-submission-list]');
        const emptyState = queue.querySelector('[data-review-empty]');
        if (countLabel) {
            countLabel.textContent = String(count);
        }
        if (list) {
            list.hidden = visibleCount === 0;
        }
        if (emptyState) {
            emptyState.hidden = visibleCount > 0;
        }
    };

    const addReviewSubmission = async (bookId) => {
        const queue = document.querySelector('[data-review-queue]');
        const list = queue?.querySelector('[data-review-submission-list]');
        if (!queue || !list || Number(queue.dataset.reviewPage) !== 1
            || queue.querySelector(`[data-review-book-id="${bookId}"]`)) {
            return;
        }

        try {
            const cardUrl = notificationConfig.reviewCardUrlTemplate.replace('__book_id__', encodeURIComponent(bookId));
            const response = await fetch(cardUrl, {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
            });
            if (!response.ok) {
                return;
            }

            const payload = await response.json();
            if (queue.querySelector(`[data-review-book-id="${payload.book_id}"]`)) {
                return;
            }

            const responseDocument = new DOMParser().parseFromString(payload.html, 'text/html');
            const card = responseDocument.querySelector('[data-review-book-id]');
            if (!card) {
                return;
            }

            list.prepend(card);
            const countLabel = queue.querySelector('[data-review-count]');
            updateReviewQueueCount(queue, Number(countLabel?.textContent || 0) + 1);
        } catch {
            // Keep the current queue usable when a new card cannot be loaded.
        }
    };

    const removeReviewSubmission = (bookId) => {
        const queue = document.querySelector('[data-review-queue]');
        const card = queue?.querySelector(`[data-review-book-id="${bookId}"]`);
        card?.remove();
        if (queue) {
            const countLabel = queue.querySelector('[data-review-count]');
            const currentCount = Number(countLabel?.textContent || 0);
            updateReviewQueueCount(queue, card ? Math.max(0, currentCount - 1) : currentCount);
        }
    };

    const pollReviewQueue = async () => {
        const queue = document.querySelector('[data-review-queue]');
        if (!queue || Number(queue.dataset.reviewPage) !== 1) {
            return;
        }

        try {
            const response = await fetch(notificationConfig.reviewPollUrl, {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
            });
            if (!response.ok) {
                return;
            }

            const { book_ids: bookIds = [], count = 0 } = await response.json();
            const pendingIds = new Set(bookIds.map(String));
            queue.querySelectorAll('[data-review-book-id]').forEach((card) => {
                if (!pendingIds.has(card.dataset.reviewBookId)) {
                    card.remove();
                }
            });
            updateReviewQueueCount(queue, Number(count));

            const currentIds = new Set([...queue.querySelectorAll('[data-review-book-id]')]
                .map((card) => card.dataset.reviewBookId));
            await Promise.all(bookIds.filter((id) => !currentIds.has(String(id))).map(addReviewSubmission));
        } catch {
            // The current queue stays usable when polling is temporarily unavailable.
        }
    };

    if (window.Echo) {
        window.Echo.private(`App.Models.User.${notificationConfig.userId}`)
            .notification((notification) => {
                receiveNotification(notification, true);
            })
            .listen('.notifications.unread-count.updated', (event) => {
                if (event.audience !== notificationConfig.audience) {
                    return;
                }

                unreadCount = Math.max(0, Number.parseInt(event.unread_count, 10) || 0);
                updateCounters();
            });

        if (notificationConfig.isAdmin) {
            window.Echo.private('admin.public-submissions')
                .listen('.public.book.submission.changed', (event) => {
                    if (event.event === 'submitted') {
                        addReviewSubmission(event.book_id);
                    } else {
                        removeReviewSubmission(event.book_id);
                    }
                });
        }
    }

    pollNotifications();
    window.setInterval(pollNotifications, 2500);

    if (notificationConfig.isAdmin && document.querySelector('[data-review-queue]')) {
        pollReviewQueue();
        window.setInterval(pollReviewQueue, 5000);
    }
}
