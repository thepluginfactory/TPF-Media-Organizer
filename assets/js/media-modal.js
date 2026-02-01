/**
 * TPF Media Organizer - Media Modal Integration
 */
(function($, wp) {
    'use strict';

    // Wait for media to be ready
    if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
        return;
    }

    // Check for our settings
    if (typeof tpfMediaOrganizerModal === 'undefined') {
        return;
    }

    var folders = tpfMediaOrganizerModal.folders || [];
    var settings = tpfMediaOrganizerModal.settings || {};
    var strings = tpfMediaOrganizerModal.strings || {};

    // Track current folder selection for AJAX filtering
    var currentModalFolder = '';

    // Use ajaxPrefilter to inject folder into AJAX requests
    $.ajaxPrefilter(function(options, originalOptions, jqXHR) {
        if (options.data && typeof options.data === 'string' && options.data.indexOf('action=query-attachments') !== -1) {
            if (currentModalFolder) {
                options.data += '&tpf_media_folder=' + encodeURIComponent(currentModalFolder);
            }
        }
    });

    /**
     * Extend AttachmentsBrowser to add folder filter
     */
    var originalAttachmentsBrowser = wp.media.view.AttachmentsBrowser;

    wp.media.view.AttachmentsBrowser = originalAttachmentsBrowser.extend({
        createToolbar: function() {
            originalAttachmentsBrowser.prototype.createToolbar.apply(this, arguments);

            // Add folder filter
            this.toolbar.set('tpfFolderFilter', new wp.media.view.TPFFolderFilter({
                controller: this.controller,
                model: this.collection.props,
                priority: -75
            }).render());
        }
    });

    /**
     * Folder Filter View
     */
    wp.media.view.TPFFolderFilter = wp.media.View.extend({
        tagName: 'select',
        className: 'attachment-filters tpf-mo-modal-filter',

        events: {
            'change': 'change'
        },

        initialize: function() {
            this.createFilters();
            wp.media.View.prototype.initialize.apply(this, arguments);

            this.listenTo(this.model, 'change:tpf_media_folder', this.select);
        },

        createFilters: function() {
            var self = this;

            this.filters = {
                all: {
                    text: strings.allFolders,
                    props: {
                        tpf_media_folder: ''
                    },
                    priority: 10
                }
            };

            // Add uncategorized
            if (settings.showUncategorized) {
                this.filters.uncategorized = {
                    text: strings.uncategorized,
                    props: {
                        tpf_media_folder: 'uncategorized'
                    },
                    priority: 20
                };
            }

            // Add folders
            var priority = 30;
            folders.forEach(function(folder) {
                var indent = '';
                for (var i = 0; i < folder.depth; i++) {
                    indent += '— ';
                }

                self.filters['folder_' + folder.id] = {
                    text: indent + folder.name + (folder.count ? ' (' + folder.count + ')' : ''),
                    props: {
                        tpf_media_folder: folder.id.toString()
                    },
                    priority: priority++
                };
            });
        },

        render: function() {
            var self = this;
            var filters = [];

            // Convert to array and sort
            Object.keys(this.filters).forEach(function(key) {
                filters.push({
                    key: key,
                    filter: self.filters[key]
                });
            });

            filters.sort(function(a, b) {
                return a.filter.priority - b.filter.priority;
            });

            // Build select options
            this.$el.empty();

            filters.forEach(function(item) {
                self.$el.append(
                    $('<option></option>')
                        .val(item.key)
                        .text(item.filter.text)
                );
            });

            this.select();

            return this;
        },

        select: function() {
            var value = this.model.get('tpf_media_folder');
            var key = 'all';

            if (value === 'uncategorized') {
                key = 'uncategorized';
            } else if (value) {
                key = 'folder_' + value;
            }

            this.$el.val(key);
        },

        change: function() {
            var key = this.$el.val();
            var filter = this.filters[key];

            if (filter) {
                // Update the tracked folder for AJAX filtering
                currentModalFolder = filter.props.tpf_media_folder || '';
                this.model.set(filter.props);
            }
        }
    });

    /**
     * Extend Attachment model to include folder in query
     */
    var originalQuery = wp.media.model.Query;

    wp.media.model.Query = originalQuery.extend({
        sync: function(method, model, options) {
            if (method === 'read') {
                options = options || {};
                options.data = options.data || {};

                var props = this.props.toJSON();

                if (props.tpf_media_folder) {
                    options.data.tpf_media_folder = props.tpf_media_folder;
                }
            }

            return originalQuery.prototype.sync.apply(this, arguments);
        }
    });

    /**
     * Extend AttachmentDetails to show folder info
     */
    var originalAttachmentDetails = wp.media.view.Attachment.Details;

    wp.media.view.Attachment.Details = originalAttachmentDetails.extend({
        render: function() {
            originalAttachmentDetails.prototype.render.apply(this, arguments);

            var model = this.model;
            var tpfFolders = model.get('tpfFolders');

            // Add folder info
            var $details = this.$el.find('.attachment-info');

            if ($details.find('.tpf-mo-attachment-folders').length === 0) {
                var $folderInfo = $('<div class="tpf-mo-attachment-folders"></div>');
                $folderInfo.append('<div class="tpf-mo-attachment-folders-label">' + strings.filterLabel + ':</div>');

                var $tags = $('<div class="tpf-mo-attachment-folder-tags"></div>');

                if (tpfFolders && tpfFolders.length > 0) {
                    tpfFolders.forEach(function(folder) {
                        $tags.append(
                            '<span class="tpf-mo-folder-tag">' +
                            '<span class="dashicons dashicons-category"></span>' +
                            escapeHtml(folder.name) +
                            '</span>'
                        );
                    });
                } else {
                    $tags.append('<span class="tpf-mo-no-folder">' + strings.uncategorized + '</span>');
                }

                $folderInfo.append($tags);
                $details.append($folderInfo);
            }

            return this;
        }
    });

    /**
     * Escape HTML helper
     */
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

})(jQuery, wp);
