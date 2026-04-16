/**
 * Module: @amdeu/label-editor/label-editor
 */
import Modal from '@typo3/backend/modal.js';
import Severity from '@typo3/backend/severity.js';

class LabelEditor {
	constructor() {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', () => this.initialize());
		} else {
			this.initialize();
		}
	}

	initialize() {
		this.initializeSelects();
		this.initializeTableSearch();
		this.initializeRemoveButtons();
		this.highlightNewLabel();
	}

	initializeSelects() {
		const sourceFileSelect = document.getElementById('sourceFile');
		const languageKeySelect = document.getElementById('languageKey');

		if (sourceFileSelect) {
			sourceFileSelect.addEventListener('change', (e) => {
				const selectedOption = e.target.options[e.target.selectedIndex];
				const url = selectedOption.dataset.url;
				if (url) {
					window.location.href = url;
				}
			});
		}

		if (languageKeySelect) {
			languageKeySelect.addEventListener('change', (e) => {
				const selectedOption = e.target.options[e.target.selectedIndex];
				const url = selectedOption.dataset.url;
				if (url) {
					window.location.href = url;
				}
			});
		}
	}

	initializeRemoveButtons() {
		document.querySelectorAll('.t3js-label-remove').forEach((link) => {
			link.addEventListener('click', (e) => {
				e.preventDefault();
				const labelKey = link.dataset.labelKey;
				const url = link.getAttribute('href');

				const title = TYPO3.lang['label_editor.removeLabel.title'] || 'Remove Label';
				const message = (TYPO3.lang['label_editor.removeLabel.message'] || 'Are you sure you want to remove the label "%s" from all languages? This action cannot be undone.')
					.replace('%s', labelKey);
				const cancelText = TYPO3.lang['label_editor.removeLabel.cancel'] || 'Cancel';
				const confirmText = TYPO3.lang['label_editor.removeLabel.confirm'] || 'Remove';

				Modal.confirm(
					title,
					message,
					Severity.warning,
					[
						{
							text: cancelText,
							active: true,
							btnClass: 'btn-default',
							name: 'cancel',
							trigger: function () {
								Modal.dismiss();
							}
						},
						{
							text: confirmText,
							btnClass: 'btn-warning',
							name: 'remove',
							trigger: function () {
								Modal.dismiss();
								window.location.href = url;
							}
						}
					]
				);
			});
		});
	}

	highlightNewLabel() {
		const row = document.querySelector('.t3js-label-highlight');
		if (!row) {
			return;
		}

		row.scrollIntoView({behavior: 'smooth', block: 'start'});
	}

	initializeTableSearch() {
		const searchInput = document.getElementById('labelSearch');
		const table = document.getElementById('labelsTable');

		if (!searchInput || !table) {
			return;
		}

		const tbody = table.querySelector('tbody');
		if (!tbody) {
			return;
		}

		const rows = tbody.querySelectorAll('tr');

		searchInput.addEventListener('input', (e) => {
			const searchTerm = e.target.value.toLowerCase().trim();
			let visibleCount = 0;

			rows.forEach(row => {
				const rowText = row.textContent.toLowerCase();

				if (searchTerm === '' || rowText.includes(searchTerm)) {
					row.style.display = '';
					visibleCount++;
				} else {
					row.style.display = 'none';
				}
			});

			this.updateNoResultsMessage(tbody, visibleCount, rows.length);
		});

		searchInput.addEventListener('keydown', (e) => {
			if (e.key === 'Escape') {
				searchInput.value = '';
				searchInput.dispatchEvent(new Event('input'));
				searchInput.blur();
			}
		});
	}

	updateNoResultsMessage(tbody, visibleCount, totalRows) {
		const existingMessage = document.querySelector('.no-results-message');
		if (existingMessage) {
			existingMessage.remove();
		}

		if (visibleCount === 0 && totalRows > 0) {
			const noResultsText = TYPO3.lang['label_editor.search.noResults'] || 'No labels match your search';
			const colCount = tbody.querySelector('tr')?.querySelectorAll('td').length || 3;
			const messageRow = document.createElement('tr');
			messageRow.className = 'no-results-message';
			messageRow.innerHTML = `
				<td colspan="${colCount}" class="text-center text-muted py-4">
					<em>${noResultsText}</em>
				</td>
			`;
			tbody.appendChild(messageRow);
		}
	}
}

export default new LabelEditor();