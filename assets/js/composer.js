(function (wp, config) {
	'use strict';

	const { __, sprintf } = wp.i18n;
	const postId = Number(config.postId || new URLSearchParams(window.location.search).get('post') || 0);
	let currentPost = null;
	let mediaItems = [];
	let attachmentIds = [];
	let removedMediaIds = new Set();
	let isReadOnly = false;

	function byId(id) {
		return document.getElementById(id);
	}

	function text(key, fallback) {
		return config.i18n?.[key] || __(fallback, 'social-media-scheduler');
	}

	function setStatus(message, tone = 'info') {
		const status = byId('sms-composer-status');
		if (!status) return;
		status.textContent = message;
		status.dataset.tone = tone;
	}

	function clearStatus() {
		setStatus('');
	}

	async function request(path, options = {}) {
		const response = await fetch(config.root + path.replace(/^\/+/, ''), {
			...options,
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce,
				...(options.headers || {}),
			},
		});
		const payload = response.status === 204 ? null : await response.json();
		if (!response.ok) {
			throw new Error(payload?.message || sprintf(__('Request failed with status %d.', 'social-media-scheduler'), response.status));
		}
		return payload;
	}

	function localDate(value) {
		if (!value) return null;
		const date = new Date(value);
		return Number.isNaN(date.getTime()) ? null : date;
	}

	function pad(value) {
		return String(value).padStart(2, '0');
	}

	function toDateTimeLocalValue(value) {
		const date = localDate(value);
		if (!date) return '';

		return [
			date.getFullYear(),
			pad(date.getMonth() + 1),
			pad(date.getDate()),
		].join('-') + 'T' + [
			pad(date.getHours()),
			pad(date.getMinutes()),
		].join(':');
	}

	function toIso(value) {
		const date = localDate(value);
		return date ? date.toISOString() : '';
	}

	function assertFutureSchedule(value) {
		const date = localDate(value);
		if (!date) {
			throw new Error(__('Date and time are required.', 'social-media-scheduler'));
		}

		if (date.getTime() <= Date.now()) {
			throw new Error(__('Date and time must be in the future.', 'social-media-scheduler'));
		}
	}

	function statusLabel(value) {
		const labels = {
			DRAFT: __('Draft', 'social-media-scheduler'),
			IN_REVIEW: __('In review', 'social-media-scheduler'),
			APPROVED: __('Approved', 'social-media-scheduler'),
			SCHEDULED: __('Scheduled', 'social-media-scheduler'),
			PUBLISHED: __('Published', 'social-media-scheduler'),
			FAILED: __('Failed', 'social-media-scheduler'),
			CANCELLED: __('Cancelled', 'social-media-scheduler'),
		};
		return labels[value] || value;
	}

	function canDeletePost(post) {
		return Boolean(post?.id) && ['SCHEDULED', 'FAILED'].includes(post.status);
	}

	function updateDeleteButton(post = currentPost) {
		const button = byId('sms-delete-post');
		if (!button) return;

		const shouldShow = canDeletePost(post);
		button.hidden = !shouldShow;
		button.disabled = !shouldShow;
	}

	function postPayload(action = 'draft') {
		const account = byId('sms-account');
		const platform = byId('sms-platform');
		const scheduledAt = byId('sms-scheduled-at')?.value || '';
		return {
			caption: byId('sms-caption')?.value || '',
			platform: platform?.value || 'instagram',
			socialAccountId: Number(account?.value || 0),
			scheduledAt: action === 'publish' ? '' : toIso(scheduledAt),
			status: action === 'draft' ? 'DRAFT' : 'PUBLISHED',
			isStory: Boolean(byId('sms-is-story')?.checked),
			notes: byId('sms-notes')?.value || '',
		};
	}

	function mediaTitle(item, attachmentId) {
		if (typeof item.title === 'object' && item.title !== null) {
			return item.title.rendered || item.title.raw || String(attachmentId);
		}

		return item.filename || item.originalName || item.title || String(attachmentId);
	}

	function normalizeMediaItem(item) {
		const hasStoredAttachmentId = Object.prototype.hasOwnProperty.call(item, 'attachmentId');
		const attachmentId = Number(hasStoredAttachmentId ? item.attachmentId : item.id || 0);
		const mediaId = hasStoredAttachmentId ? Number(item.id || 0) : Number(item.mediaId || 0);

		return {
			...item,
			attachmentId,
			mediaId,
			displayTitle: mediaTitle(item, attachmentId),
			thumb: item.sizes?.thumbnail?.url || item.url || item.icon || '',
		};
	}

	function setMediaItems(items) {
		mediaItems = items.map(normalizeMediaItem).filter((item) => item.attachmentId > 0);
		attachmentIds = mediaItems.map((item) => item.attachmentId);
		renderMediaList();
	}

	function renderMediaList() {
		const list = byId('sms-media-list');
		if (!list) return;
		list.replaceChildren();

		mediaItems.forEach((item) => {
			const row = document.createElement('li');
			row.className = 'sms-media-list__item';
			row.dataset.attachmentId = String(item.attachmentId);
			if (item.mediaId) {
				row.dataset.mediaId = String(item.mediaId);
			}

			if (item.thumb) {
				const image = document.createElement('img');
				image.src = item.thumb;
				image.alt = '';
				row.appendChild(image);
			} else {
				const icon = document.createElement('span');
				icon.className = 'dashicons dashicons-format-image';
				icon.setAttribute('aria-hidden', 'true');
				row.appendChild(icon);
			}

			const title = document.createElement('span');
			title.textContent = item.displayTitle;
			row.appendChild(title);

			const remove = document.createElement('button');
			remove.className = 'button-link-delete';
			remove.type = 'button';
			remove.textContent = __('Remove', 'social-media-scheduler');
			remove.hidden = isReadOnly;
			remove.addEventListener('click', () => {
				if (isReadOnly) return;
				if (item.mediaId) {
					removedMediaIds.add(item.mediaId);
				}
				setMediaItems(mediaItems.filter((media) => media.attachmentId !== item.attachmentId));
			});
			row.appendChild(remove);

			list.appendChild(row);
		});
	}

	function openMediaPicker() {
		if (isReadOnly) return;

		const existingMediaIds = mediaItems.map((item) => item.mediaId).filter(Boolean);
		const frame = wp.media({
			title: __('Choose Media', 'social-media-scheduler'),
			button: { text: __('Use selected media', 'social-media-scheduler') },
			library: { type: ['image', 'video'] },
			multiple: true,
		});
		frame.on('select', () => {
			existingMediaIds.forEach((mediaId) => removedMediaIds.add(mediaId));
			setMediaItems(frame.state().get('selection').toJSON());
		});
		frame.open();
	}

	function setAccountSelection(post) {
		const account = byId('sms-account');
		const platform = byId('sms-platform');
		if (platform) {
			platform.value = post.platform || platform.value || 'instagram';
		}
		if (!account) return;

		const accountId = String(post.socialAccountId || '');
		let matched = false;
		Array.from(account.options).forEach((option) => {
			const selected = option.value === accountId && option.dataset.platform === post.platform;
			option.selected = selected;
			matched = matched || selected;
		});

		if (!matched) {
			account.value = accountId;
		}
	}

	function field(id) {
		return byId(id);
	}

	function setMediaPanelVisible(visible) {
		const panel = field('sms-media-panel');
		const grid = field('sms-composer-form-grid');

		if (panel) {
			panel.hidden = !visible;
		}

		if (grid) {
			grid.classList.toggle('sms-form-grid--single', !visible);
		}
	}

	function syncStoryCaptionState() {
		const caption = field('sms-caption');
		if (!caption) return;

		const isStory = Boolean(field('sms-is-story')?.checked);
		caption.disabled = isStory;
	}

	function setReadOnly(readOnly) {
		isReadOnly = readOnly;

		['sms-caption', 'sms-scheduled-at', 'sms-notes'].forEach((id) => {
			const control = field(id);
			if (control) {
				control.readOnly = readOnly;
			}
		});

		const account = field('sms-account');
		if (account) {
			account.disabled = readOnly || account.options.length <= 1;
		}

		const story = field('sms-is-story');
		if (story) {
			story.disabled = readOnly;
		}

		const mediaPicker = field('sms-media-picker');
		if (mediaPicker) {
			mediaPicker.hidden = readOnly;
		}

		document.querySelectorAll('#sms-composer-form .sms-actions button').forEach((button) => {
			button.hidden = readOnly;
		});
		syncStoryCaptionState();
		renderMediaList();
	}

	function publishErrors(post) {
		const errors = (Array.isArray(post?.publishResults) ? post.publishResults : [])
			.filter((result) => result?.error || result?.status === 'failed')
			.map((result) => String(result.error || '').trim())
			.filter(Boolean);

		const uniqueErrors = Array.from(new Set(errors));
		if (uniqueErrors.length > 0) {
			return uniqueErrors;
		}

		return post?.status === 'FAILED'
			? [__('Publishing failed, but no platform error was stored.', 'social-media-scheduler')]
			: [];
	}

	function postPermalink(post) {
		const results = Array.isArray(post?.publishResults) ? post.publishResults : [];
		const success = results.find((result) => result.status === 'success' && result.permalink);
		const anyPermalink = results.find((result) => result.permalink);

		return success?.permalink || anyPermalink?.permalink || '';
	}

	function updateStatusCard(post) {
		const card = byId('sms-post-status-card');
		const title = byId('sms-post-status-title');
		const message = byId('sms-post-status-message');
		const list = byId('sms-post-errors');
		const viewPost = byId('sms-view-post');
		if (!card || !title || !message || !list) return;

		const label = statusLabel(post.status);
		const errors = publishErrors(post);
		card.hidden = false;
		card.classList.toggle('sms-composer-state--error', post.status === 'FAILED');
		list.replaceChildren();
		list.hidden = true;

		if (post.status === 'FAILED') {
			title.textContent = text('publishingErrors', 'Publishing errors');
			message.textContent = text('fixFieldsAndRetry', 'Fix the fields below, then publish or schedule the post again.');
			errors.forEach((error) => {
				const item = document.createElement('li');
				item.textContent = error;
				list.appendChild(item);
			});
			list.hidden = errors.length === 0;
		} else if (post.status === 'PUBLISHED') {
			title.textContent = text('publishedPost', 'Published post');
			message.textContent = sprintf(
				/* translators: %s: post status label. */
				text('publishedReadOnly', 'Status: %s. Published posts are read-only.'),
				label
			);
		} else {
			title.textContent = sprintf(
				/* translators: %s: post status label. */
				text('postStatus', 'Post status: %s'),
				label
			);
			message.textContent = text('readOnlyCurrent', 'This post is read-only in its current status.');
		}

		if (viewPost) {
			const permalink = post.status === 'PUBLISHED' ? postPermalink(post) : '';
			viewPost.hidden = !permalink;
			viewPost.href = permalink || '#';
		}
	}

	function populatePost(post) {
		currentPost = post;
		byId('sms-composer-eyebrow').textContent = text('editPostEyebrow', 'Edit post');
		byId('sms-composer-title').textContent = text('editPostTitle', 'Edit Post');
		byId('sms-caption').value = post.caption || '';
		setAccountSelection(post);
		byId('sms-is-story').checked = Boolean(post.isStory);
		syncStoryCaptionState();
		byId('sms-scheduled-at').value = toDateTimeLocalValue(post.scheduledAt);
		byId('sms-notes').value = post.notes || '';
		setMediaItems(Array.isArray(post.media) ? post.media : []);
		updateStatusCard(post);
		setMediaPanelVisible(post.status !== 'PUBLISHED');
		setReadOnly(post.status !== 'FAILED');
		updateDeleteButton(post);
	}

	function editUrl(id) {
		const url = new URL(config.adminUrl, window.location.origin);
		url.searchParams.set('page', 'sms-new-post');
		url.searchParams.set('post', String(id));
		return url.toString();
	}

	function switchToEditUrl(id) {
		if (!window.history?.replaceState || postId) return;
		window.history.replaceState({}, '', editUrl(id));
	}

	async function syncMedia(post) {
		for (const mediaId of removedMediaIds) {
			await request(`posts/${post.id}/media/${mediaId}`, { method: 'DELETE' });
		}
		removedMediaIds = new Set();

		for (const attachmentId of attachmentIds) {
			await request(`posts/${post.id}/media`, {
				method: 'POST',
				body: JSON.stringify({ attachmentId }),
			});
		}

		if (attachmentIds.length > 0) {
			await request(`posts/${post.id}/media/reorder`, {
				method: 'POST',
				body: JSON.stringify({ attachmentIds }),
			});
		}

		return request(`posts/${post.id}`);
	}

	async function savePost(action = 'draft') {
		if (isReadOnly) {
			throw new Error(text('postReadOnly', 'This post is read-only.'));
		}

		const scheduledAt = byId('sms-scheduled-at')?.value || '';
		if (action === 'schedule') {
			assertFutureSchedule(scheduledAt);
		}

		const payload = postPayload(action);
		const post = currentPost?.id
			? await request(`posts/${currentPost.id}`, {
				method: 'PATCH',
				body: JSON.stringify(payload),
			})
			: await request('posts', {
				method: 'POST',
				body: JSON.stringify(payload),
			});

		switchToEditUrl(post.id);
		const refreshed = await syncMedia(post);
		currentPost = refreshed;
		return refreshed;
	}

	function assertPublishResult(result) {
		const results = Array.isArray(result) ? result : [result];
		const errors = results.map((item) => item?.error).filter(Boolean);
		if (errors.length > 0) {
			throw new Error(errors.join('\n'));
		}
	}

	async function publishPost(post) {
		let result;
		if (post.platform === 'tiktok') {
			result = await request('publish/tiktok', {
				method: 'POST',
				body: JSON.stringify({ postId: post.id }),
			});
			assertPublishResult(result);
			return result;
		}

		result = await request('publish/meta', {
			method: 'POST',
			body: JSON.stringify({ postId: post.id, targetPlatforms: [post.platform] }),
		});
		assertPublishResult(result);
		return result;
	}

	async function refreshCurrentPost() {
		if (!currentPost?.id) return;
		const post = await request(`posts/${currentPost.id}`);
		populatePost(post);
	}

	async function deleteCurrentPost() {
		if (!canDeletePost(currentPost)) {
			throw new Error(text('deleteNotAllowed', 'Only scheduled or failed posts can be deleted.'));
		}

		const confirmed = window.confirm(
			text('deleteConfirm', 'Are you sure you want to delete this post? This action cannot be undone.')
		);
		if (!confirmed) {
			return;
		}

		setStatus(text('deletingPost', 'Deleting post...'));
		await request(`posts/${currentPost.id}`, { method: 'DELETE' });
		setStatus(text('postDeleted', 'Post deleted.'), 'success');
		window.location.href = config.calendarUrl + '&sms_notice=deleted';
	}

	async function loadPost() {
		if (!postId) return;

		try {
			setStatus(text('loadingPost', 'Loading post...'));
			const post = await request(`posts/${postId}`);
			populatePost(post);
			clearStatus();
		} catch (error) {
			setStatus(error.message, 'error');
			setReadOnly(true);
			updateDeleteButton(null);
		}
	}

	document.addEventListener('DOMContentLoaded', () => {
		const account = byId('sms-account');
		const platform = byId('sms-platform');
		account?.addEventListener('change', () => {
			const option = account.selectedOptions?.[0];
			if (platform && option?.dataset.platform) {
				platform.value = option.dataset.platform;
			}
		});

		byId('sms-is-story')?.addEventListener('change', syncStoryCaptionState);

		setReadOnly(false);
		syncStoryCaptionState();
		setMediaPanelVisible(true);
		updateDeleteButton(null);
		byId('sms-media-picker')?.addEventListener('click', openMediaPicker);

		byId('sms-composer-form')?.addEventListener('submit', async (event) => {
			event.preventDefault();
			try {
				setStatus(text('savingAsDraft', 'Saving as draft...'));
				const post = await savePost('draft');
				populatePost(post);
				setStatus(text('draftSaved', 'Draft saved.'), 'success');
			} catch (error) {
				setStatus(error.message, 'error');
			}
		});

		byId('sms-schedule-post')?.addEventListener('click', async () => {
			try {
				setStatus(text('savingAndScheduling', 'Saving and scheduling...'));
				const post = await savePost('schedule');
				try {
					await publishPost(post);
					window.location.href = config.calendarUrl + '&sms_notice=scheduled';
				} catch (publishError) {
					await refreshCurrentPost();
					setStatus(publishError.message, 'error');
				}
			} catch (error) {
				setStatus(error.message, 'error');
			}
		});

		byId('sms-publish-now')?.addEventListener('click', async () => {
			try {
				setStatus(text('savingAndPublishing', 'Saving and publishing...'));
				const post = await savePost('publish');
				try {
					await publishPost(post);
					window.location.href = config.calendarUrl + '&sms_notice=published';
				} catch (publishError) {
					await refreshCurrentPost();
					setStatus(publishError.message, 'error');
				}
			} catch (error) {
				setStatus(error.message, 'error');
			}
		});

		byId('sms-delete-post')?.addEventListener('click', async () => {
			try {
				await deleteCurrentPost();
			} catch (error) {
				setStatus(error.message || text('deleteFailed', 'Failed to delete post.'), 'error');
			}
		});

		loadPost();
	});
})(window.wp, window.smsComposer || {});
