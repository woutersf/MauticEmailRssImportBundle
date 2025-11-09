/**
 * RSS Import functionality for Mautic Email Builder
 */
(function() {
    'use strict';

    var rssData = null;
    var htmlTemplate = '';

    function addRssButton() {
        // Check if we're on an email builder page
        if (!document.querySelector('.builder') && !document.querySelector('#emailform_customHtml')) {
            return;
        }

        // Check if button already exists
        if (document.getElementById('rss-import-button')) {
            return;
        }

        // Find the toolbar - try multiple selectors for different builder types
        var toolbar = document.querySelector('.fr-toolbar') ||
                     document.querySelector('.froala-editor .fr-box .fr-toolbar') ||
                     document.querySelector('.builder-panel .toolbar');

        if (!toolbar) {
            console.log('RSS Import: Toolbar not found, trying again later...');
            return;
        }

        // Create RSS import button
        var buttonHtml = '<button type="button" id="rss-import-button" class="btn btn-default btn-sm" title="Import RSS Feed Items">' +
                        '<i class="fa fa-rss"></i> RSS Import' +
                        '</button>';

        // Insert button into toolbar
        var buttonContainer = document.createElement('div');
        buttonContainer.innerHTML = buttonHtml;
        buttonContainer.style.display = 'inline-block';
        buttonContainer.style.marginLeft = '5px';

        toolbar.appendChild(buttonContainer);

        // Attach click event
        document.getElementById('rss-import-button').addEventListener('click', function(e) {
            e.preventDefault();
            fetchRssItems();
        });

        console.log('RSS Import: Button added to toolbar');
    }

    function fetchRssItems() {
        // Show loading indicator
        showLoadingModal();

        // Fetch RSS items from backend
        fetch(mauticBaseUrl + '/s/emailrssimport/fetch', {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert('Error fetching RSS feed: ' + data.error);
                closeModal();
                return;
            }

            rssData = data.items;
            htmlTemplate = data.template;
            showRssModal(data.items);
        })
        .catch(error => {
            alert('Error: ' + error.message);
            closeModal();
        });
    }

    function showLoadingModal() {
        var modalHtml = `
            <div class="modal fade in" id="rss-import-modal" style="display: block;">
                <div class="modal-backdrop fade in"></div>
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" onclick="document.getElementById('rss-import-modal').remove()">
                                <span>&times;</span>
                            </button>
                            <h4 class="modal-title">RSS Feed Import</h4>
                        </div>
                        <div class="modal-body">
                            <div class="text-center">
                                <i class="fa fa-spinner fa-spin fa-3x"></i>
                                <p class="mt-3">Loading RSS feed...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        var modalContainer = document.createElement('div');
        modalContainer.innerHTML = modalHtml;
        document.body.appendChild(modalContainer.firstElementChild);
    }

    function showRssModal(items) {
        if (!items || items.length === 0) {
            alert('No items found in RSS feed');
            closeModal();
            return;
        }

        var itemsHtml = items.map(function(item, index) {
            var title = item.title || 'Untitled';
            var description = item.description || '';
            var pubDate = item.pubDate || '';

            // Truncate description
            if (description.length > 150) {
                description = description.substring(0, 150) + '...';
            }

            return `
                <div class="rss-item" style="border: 1px solid #ddd; padding: 15px; margin-bottom: 10px; border-radius: 4px;">
                    <label style="display: block; cursor: pointer; margin: 0;">
                        <input type="checkbox" class="rss-item-checkbox" value="${index}" style="margin-right: 10px;">
                        <strong>${title}</strong>
                        ${pubDate ? '<small class="text-muted" style="margin-left: 10px;">' + pubDate + '</small>' : ''}
                        ${description ? '<div style="margin-top: 5px; margin-left: 25px; color: #666;">' + description + '</div>' : ''}
                    </label>
                </div>
            `;
        }).join('');

        var modalHtml = `
            <div class="modal fade in" id="rss-import-modal" style="display: block;">
                <div class="modal-backdrop fade in"></div>
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" onclick="document.getElementById('rss-import-modal').remove()">
                                <span>&times;</span>
                            </button>
                            <h4 class="modal-title">Select RSS Items to Import</h4>
                        </div>
                        <div class="modal-body" style="max-height: 500px; overflow-y: auto;">
                            <div class="mb-3">
                                <button type="button" class="btn btn-default btn-sm" id="select-all-items">Select All</button>
                                <button type="button" class="btn btn-default btn-sm" id="deselect-all-items">Deselect All</button>
                            </div>
                            <div id="rss-items-list">
                                ${itemsHtml}
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" onclick="document.getElementById('rss-import-modal').remove()">Cancel</button>
                            <button type="button" class="btn btn-primary" id="insert-rss-items">Insert Selected Items</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Remove existing modal if any
        closeModal();

        // Add new modal
        var modalContainer = document.createElement('div');
        modalContainer.innerHTML = modalHtml;
        document.body.appendChild(modalContainer.firstElementChild);

        // Attach event handlers
        document.getElementById('select-all-items').addEventListener('click', function() {
            document.querySelectorAll('.rss-item-checkbox').forEach(function(cb) {
                cb.checked = true;
            });
        });

        document.getElementById('deselect-all-items').addEventListener('click', function() {
            document.querySelectorAll('.rss-item-checkbox').forEach(function(cb) {
                cb.checked = false;
            });
        });

        document.getElementById('insert-rss-items').addEventListener('click', insertSelectedItems);
    }

    function closeModal() {
        var modal = document.getElementById('rss-import-modal');
        if (modal) {
            modal.remove();
        }
    }

    function insertSelectedItems() {
        var checkboxes = document.querySelectorAll('.rss-item-checkbox:checked');

        if (checkboxes.length === 0) {
            alert('Please select at least one item to insert');
            return;
        }

        var selectedIndices = Array.from(checkboxes).map(function(cb) {
            return parseInt(cb.value);
        }).sort(function(a, b) { return a - b; }); // Sort to maintain feed order

        var htmlContent = '';

        selectedIndices.forEach(function(index) {
            var item = rssData[index];
            var itemHtml = htmlTemplate;

            // Replace tokens
            Object.keys(item).forEach(function(key) {
                var token = '{' + key + '}';
                var value = item[key] || '';
                itemHtml = itemHtml.split(token).join(value);
            });

            htmlContent += itemHtml + '\n';
        });

        // Insert content into editor
        insertIntoEditor(htmlContent);

        closeModal();
    }

    function insertIntoEditor(content) {
        // Try to find the active editor instance
        // Support for different editor types (Froala, textarea, etc.)

        // Try Froala editor first
        if (typeof $ !== 'undefined' && $.FroalaEditor) {
            var $editor = $('.fr-element');
            if ($editor.length > 0) {
                $editor.froalaEditor('html.insert', content);
                return;
            }
        }

        // Fallback: Try to find textarea for custom HTML
        var textarea = document.querySelector('#emailform_customHtml') ||
                      document.querySelector('textarea[name="emailform[customHtml]"]');

        if (textarea) {
            // Insert at cursor position or at the end
            var startPos = textarea.selectionStart;
            var endPos = textarea.selectionEnd;
            var textBefore = textarea.value.substring(0, startPos);
            var textAfter = textarea.value.substring(endPos, textarea.value.length);

            textarea.value = textBefore + content + textAfter;
            textarea.selectionStart = textarea.selectionEnd = startPos + content.length;
            textarea.focus();
            return;
        }

        // Last resort: try to insert via execCommand
        try {
            document.execCommand('insertHTML', false, content);
        } catch (e) {
            alert('Could not insert content. Please ensure you have an active editor.');
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', addRssButton);
    } else {
        addRssButton();
    }

    // Also try after delays in case DOM updates dynamically
    setTimeout(addRssButton, 1000);
    setTimeout(addRssButton, 2000);
    setTimeout(addRssButton, 3000);
})();
