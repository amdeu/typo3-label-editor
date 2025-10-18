/**
 * Module: @ubos/label-editor/label-editor
 */
class LabelEditor {
	constructor() {
		// Wait for DOM to be ready
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', () => this.initialize());
		} else {
			this.initialize();
		}
	}

	initialize() {
		console.log('LabelEditor: Initializing...');
		this.initializeSelects();
		this.initializeTableSearch();
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

	initializeTableSearch() {
		const searchInput = document.getElementById('labelSearch');
		const table = document.getElementById('labelsTable');

		console.log('Search input found:', !!searchInput);
		console.log('Table found:', !!table);

		if (!searchInput || !table) {
			console.warn('LabelEditor: Search input or table not found');
			return;
		}

		const tbody = table.querySelector('tbody');
		if (!tbody) {
			console.warn('LabelEditor: tbody not found');
			return;
		}

		const rows = tbody.querySelectorAll('tr');
		console.log('Found rows:', rows.length);

		searchInput.addEventListener('input', (e) => {
			console.log('Search input event triggered:', e.target.value);
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

			console.log('Visible rows:', visibleCount);
			this.updateNoResultsMessage(tbody, visibleCount, rows.length);
		});

		// Clear search on Escape key
		searchInput.addEventListener('keydown', (e) => {
			if (e.key === 'Escape') {
				searchInput.value = '';
				searchInput.dispatchEvent(new Event('input'));
				searchInput.blur();
			}
		});

		console.log('LabelEditor: Table search initialized');
	}

	updateNoResultsMessage(tbody, visibleCount, totalRows) {
		// Remove existing message if any
		const existingMessage = document.querySelector('.no-results-message');
		if (existingMessage) {
			existingMessage.remove();
		}

		// Add message if no results
		if (visibleCount === 0 && totalRows > 0) {
			const colCount = tbody.querySelector('tr')?.querySelectorAll('td').length || 3;
			const messageRow = document.createElement('tr');
			messageRow.className = 'no-results-message';
			messageRow.innerHTML = `
        <td colspan="${colCount}" class="text-center text-muted py-4">
          <em>No labels match your search</em>
        </td>
      `;
			tbody.appendChild(messageRow);
		}
	}
}

export default new LabelEditor();