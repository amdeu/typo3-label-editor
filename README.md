# TYPO3 Label Editor

A TYPO3 backend module for editing translation labels (locallang files) without modifying the original extension files.

## Features

- 📝 **Edit labels** - Customize any label from any extension
- 🌍 **Multi-language support** - Edit multiple languages simultaneously
- ➕ **Add custom labels** - Create new labels that don't exist in the original files
- 🔍 **Search & filter** - Quickly find labels
- 🎯 **Non-destructive** - Original extension files remain untouched

Works with [locallang_formats](https://github.com/amdeu/typo3-locallang-formats) if you want to use YAML, JSON, or PHP array files instead of XLF.

## 📋 Requirements

- TYPO3 13.4
- PHP 8.2

## 📦 Installation
```bash
composer require amdeu/typo3-label-editor
```

## 🛠️ Usage

### Access the Module

Navigate to **Site Management → Label Editor** in the TYPO3 backend.

### Add an Extension

1. Find the extension you want to edit in the "Add Extension" section
2. Click the "+" button to add it to management
3. The extension's locallang files are now available for editing

### Edit Labels

1. Select an extension from the "Managed Extensions" list
2. Choose the label file from the dropdown
3. Select one or more languages using the checkboxes
4. Edit labels in the table:
    - **Single language view**: Shows original and custom override
    - **Multi-language view**: Shows override for each selected language
5. Click "Save" in the document header

### Add Custom Labels

1. Enter a label key (e.g., `my.custom.label`) in the input field
2. Click "+"
3. The label is created across all language files
4. Fill in the values and save

### Search Labels

Use the search box above the table to filter labels by key or value in real-time.

### Remove an Extension

1. Click the trash icon next to a managed extension
2. Confirm the warning dialog
3. **All custom label overrides for that extension will be deleted**


## 💡 How It Works

The extension uses TYPO3's `locallangXMLOverride`/`resourceOverrides` feature:

1. When you add an extension, a **registry** is created at `var/label_editor/registry.json`
2. Custom labels are stored in `var/label_editor/overrides/{extension}/{file}.xlf`
3. TYPO3 merges these override files with the original locallang files
4. Original extension files remain unchanged

