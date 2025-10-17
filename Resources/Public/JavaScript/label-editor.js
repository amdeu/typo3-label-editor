/**
 * Module: @ubos/label-editor/label-editor
 */
class LabelEditor {
	constructor() {
		this.initialize();
	}

	initialize() {
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
}

export default new LabelEditor();