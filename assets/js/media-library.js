/**
 * TPF Media Organizer - Media Library Integration
 */
(function($) {
    'use strict';

    // Main object
    var TPF_MediaOrganizer = {
        // Current state
        currentFolder: null,
        folders: [],
        selectedAttachments: [],

        /**
         * Initialize
         */
        init: function() {
            if (typeof tpfMediaOrganizer === 'undefined') {
                return;
            }

            this.folders = tpfMediaOrganizer.folders || [];
            this.settings = tpfMediaOrganizer.settings || {};
            this.strings = tpfMediaOrganizer.strings || {};

            // Get current folder from URL
            var urlParams = new URLSearchParams(window.location.search);
            this.currentFolder = urlParams.get('tpf_media_folder') || 'all';

            this.hookMediaLibraryAjax();
            this.createSidebar();
            this.bindEvents();
            this.initDragDrop();
        },

        /**
         * Hook into WordPress media library AJAX to filter by folder
         */
        hookMediaLibraryAjax: function() {
            var self = this;

            // Use ajaxPrefilter to modify request BEFORE it's sent
            $.ajaxPrefilter(function(options, originalOptions, jqXHR) {
                if (options.data && typeof options.data === 'string' && options.data.indexOf('action=query-attachments') !== -1) {
                    // Add our folder filter to the request
                    if (self.currentFolder && self.currentFolder !== 'all') {
                        options.data += '&tpf_media_folder=' + encodeURIComponent(self.currentFolder);
                    }
                }
            });
        },

        /**
         * Create sidebar HTML
         */
        createSidebar: function() {
            var self = this;

            // Create sidebar container
            var $sidebar = $('<div class="tpf-mo-sidebar"></div>');

            // Header
            var $header = $('<div class="tpf-mo-sidebar-header">' +
                '<h3 class="tpf-mo-sidebar-title">' + this.strings.moveToFolder + '</h3>' +
                '<button type="button" class="tpf-mo-add-folder-btn" title="' + this.strings.newFolder + '">' +
                '<span class="dashicons dashicons-plus-alt2"></span>' +
                '</button>' +
                '</div>');

            // New folder form
            var $newFolderForm = $('<div class="tpf-mo-new-folder-form">' +
                '<input type="text" class="tpf-mo-new-folder-input" placeholder="' + this.strings.folderName + '">' +
                '<div class="tpf-mo-new-folder-actions">' +
                '<button type="button" class="button button-primary tpf-mo-create-folder-btn">' + this.strings.create + '</button>' +
                '<button type="button" class="button tpf-mo-cancel-folder-btn">' + this.strings.cancel + '</button>' +
                '</div>' +
                '</div>');

            // Folder tree
            var $tree = $('<ul class="tpf-mo-folder-tree"></ul>');

            // Add "All Media" option
            $tree.append(this.createFolderItem({
                id: 'all',
                name: this.strings.allMedia,
                count: null,
                children: []
            }, true));

            // Add "Uncategorized" option
            if (this.settings.showUncategorized) {
                $tree.append(this.createFolderItem({
                    id: 'uncategorized',
                    name: this.strings.uncategorized,
                    count: tpfMediaOrganizer.uncategorizedCount || 0,
                    children: []
                }));
            }

            // Add folders
            this.folders.forEach(function(folder) {
                $tree.append(self.createFolderItem(folder));
            });

            $sidebar.append($header).append($newFolderForm).append($tree);

            // Insert sidebar and add body class to offset content
            $('body').addClass('tpf-mo-has-sidebar').append($sidebar);

            // Create context menu
            this.createContextMenu();

            // Create toast container
            $('body').append('<div class="tpf-mo-toast"></div>');

            // Set initial active state based on URL
            this.setActiveFromUrl();
        },

        /**
         * Create folder item HTML
         */
        createFolderItem: function(folder, isAll) {
            var self = this;
            var hasChildren = folder.children && folder.children.length > 0;

            var $item = $('<li class="tpf-mo-folder-item" data-folder-id="' + folder.id + '"></li>');

            // Toggle for children
            if (hasChildren) {
                $item.append('<span class="tpf-mo-folder-toggle"><span class="dashicons dashicons-arrow-down-alt2"></span></span>');

                if (!this.settings.folderTreeExpanded) {
                    $item.addClass('collapsed');
                }
            }

            // Folder link
            var iconClass = isAll ? 'dashicons-portfolio' : 'dashicons-category';
            var $link = $('<a class="tpf-mo-folder-link" href="#">' +
                '<span class="dashicons ' + iconClass + '"></span>' +
                '<span class="tpf-mo-folder-name">' + this.escapeHtml(folder.name) + '</span>' +
                (this.settings.showFolderCount && folder.count !== null ? '<span class="tpf-mo-folder-count">' + folder.count + '</span>' : '') +
                '</a>');

            $item.append($link);

            // Children
            if (hasChildren) {
                var $children = $('<ul class="tpf-mo-folder-children"></ul>');
                folder.children.forEach(function(child) {
                    $children.append(self.createFolderItem(child));
                });
                $item.append($children);
            }

            return $item;
        },

        /**
         * Create context menu
         */
        createContextMenu: function() {
            var $menu = $('<div class="tpf-mo-context-menu">' +
                '<div class="tpf-mo-context-menu-item" data-action="rename">' +
                '<span class="dashicons dashicons-edit"></span>' + this.strings.rename +
                '</div>' +
                '<div class="tpf-mo-context-menu-item" data-action="add-subfolder">' +
                '<span class="dashicons dashicons-plus"></span>' + this.strings.newFolder +
                '</div>' +
                '<div class="tpf-mo-context-menu-item danger" data-action="delete">' +
                '<span class="dashicons dashicons-trash"></span>' + this.strings.delete +
                '</div>' +
                '</div>');

            $('body').append($menu);
        },

        /**
         * Set active folder from URL
         */
        setActiveFromUrl: function() {
            $('.tpf-mo-folder-link').removeClass('active');

            if (this.currentFolder && this.currentFolder !== 'all') {
                $('.tpf-mo-folder-item[data-folder-id="' + this.currentFolder + '"] > .tpf-mo-folder-link').addClass('active');
            } else {
                $('.tpf-mo-folder-item[data-folder-id="all"] > .tpf-mo-folder-link').addClass('active');
            }
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            var self = this;

            // Folder click
            $(document).on('click', '.tpf-mo-folder-link', function(e) {
                e.preventDefault();
                var folderId = $(this).closest('.tpf-mo-folder-item').data('folder-id');
                self.filterByFolder(folderId);
            });

            // Toggle children
            $(document).on('click', '.tpf-mo-folder-toggle', function(e) {
                e.stopPropagation();
                $(this).closest('.tpf-mo-folder-item').toggleClass('collapsed');
            });

            // Add folder button
            $(document).on('click', '.tpf-mo-add-folder-btn', function() {
                $('.tpf-mo-new-folder-form').addClass('active');
                $('.tpf-mo-new-folder-input').focus();
            });

            // Cancel new folder
            $(document).on('click', '.tpf-mo-cancel-folder-btn', function() {
                $('.tpf-mo-new-folder-form').removeClass('active');
                $('.tpf-mo-new-folder-input').val('');
            });

            // Create folder
            $(document).on('click', '.tpf-mo-create-folder-btn', function() {
                var name = $('.tpf-mo-new-folder-input').val().trim();
                if (name) {
                    self.createFolder(name);
                }
            });

            // Enter key in new folder input
            $(document).on('keypress', '.tpf-mo-new-folder-input', function(e) {
                if (e.which === 13) {
                    var name = $(this).val().trim();
                    if (name) {
                        self.createFolder(name);
                    }
                }
            });

            // Context menu
            $(document).on('contextmenu', '.tpf-mo-folder-item:not([data-folder-id="all"]):not([data-folder-id="uncategorized"]) > .tpf-mo-folder-link', function(e) {
                e.preventDefault();
                var folderId = $(this).closest('.tpf-mo-folder-item').data('folder-id');
                self.showContextMenu(e.pageX, e.pageY, folderId);
            });

            // Context menu actions
            $(document).on('click', '.tpf-mo-context-menu-item', function() {
                var action = $(this).data('action');
                var folderId = $('.tpf-mo-context-menu').data('folder-id');

                switch (action) {
                    case 'rename':
                        self.startRename(folderId);
                        break;
                    case 'add-subfolder':
                        self.showSubfolderForm(folderId);
                        break;
                    case 'delete':
                        self.deleteFolder(folderId);
                        break;
                }

                self.hideContextMenu();
            });

            // Hide context menu on click elsewhere
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.tpf-mo-context-menu').length) {
                    self.hideContextMenu();
                }
            });

            // Escape key
            $(document).on('keyup', function(e) {
                if (e.key === 'Escape') {
                    self.hideContextMenu();
                    $('.tpf-mo-new-folder-form').removeClass('active');
                    self.cancelRename();
                }
            });
        },

        /**
         * Initialize drag and drop
         */
        initDragDrop: function() {
            if (!this.settings.enableDragDrop) {
                return;
            }

            var self = this;

            // Make attachments draggable
            $(document).on('mouseenter', '.attachments .attachment', function() {
                if (!$(this).data('ui-draggable')) {
                    $(this).draggable({
                        helper: function() {
                            var $selected = $('.attachments .attachment.selected');
                            if ($selected.length === 0) {
                                $selected = $(this);
                            }

                            var count = $selected.length;
                            var $helper = $('<div class="tpf-mo-drag-helper">' +
                                '<span class="dashicons dashicons-format-image"></span>' +
                                '<span class="count">' + count + ' item' + (count > 1 ? 's' : '') + '</span>' +
                                '</div>');

                            self.selectedAttachments = $selected.map(function() {
                                return $(this).data('id');
                            }).get();

                            return $helper;
                        },
                        cursor: 'move',
                        cursorAt: { top: 20, left: 20 },
                        revert: 'invalid',
                        appendTo: 'body',
                        zIndex: 10000,
                        start: function() {
                            $(this).addClass('tpf-mo-dragging');
                        },
                        stop: function() {
                            $(this).removeClass('tpf-mo-dragging');
                        }
                    });
                }
            });

            // Make folders droppable
            $(document).on('mouseenter', '.tpf-mo-folder-link', function() {
                var $item = $(this).closest('.tpf-mo-folder-item');
                var folderId = $item.data('folder-id');

                if (folderId === 'all') {
                    return;
                }

                if (!$(this).data('ui-droppable')) {
                    $(this).droppable({
                        accept: '.attachment',
                        hoverClass: 'drag-over',
                        tolerance: 'pointer',
                        drop: function(event, ui) {
                            var targetFolderId = folderId === 'uncategorized' ? 0 : folderId;
                            self.assignToFolder(self.selectedAttachments, targetFolderId);
                        }
                    });
                }
            });
        },

        /**
         * Refresh the media library grid with current folder filter
         */
        refreshMediaLibrary: function() {
            var self = this;

            // Wait a short moment for WordPress media library to initialize
            setTimeout(function() {
                if (typeof wp !== 'undefined' && wp.media && wp.media.frame) {
                    var library = wp.media.frame.state().get('library');
                    if (library) {
                        library.props.set({tpf_media_folder: self.currentFolder === 'all' ? '' : self.currentFolder});
                        library.reset();
                        library.more();
                    }
                }
            }, 100);
        },

        /**
         * Filter media by folder
         */
        filterByFolder: function(folderId) {
            var self = this;

            // Update current folder
            this.currentFolder = folderId;

            // Update URL without full page reload
            var url = new URL(window.location.href);
            if (folderId === 'all') {
                url.searchParams.delete('tpf_media_folder');
            } else {
                url.searchParams.set('tpf_media_folder', folderId);
            }
            window.history.pushState({}, '', url.toString());

            // Update active state in sidebar
            this.setActiveFromUrl();

            // Refresh the media library grid
            if (typeof wp !== 'undefined' && wp.media && wp.media.frame) {
                // Clear selection and re-fetch
                var library = wp.media.frame.state().get('library');
                if (library) {
                    library.props.set({tpf_media_folder: folderId === 'all' ? '' : folderId});
                    library.reset();
                    library.more();
                }
            } else {
                // Fallback: reload page
                window.location.href = url.toString();
            }
        },

        /**
         * Create folder
         */
        createFolder: function(name, parentId) {
            var self = this;

            $.ajax({
                url: tpfMediaOrganizer.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'tpf_mo_create_folder',
                    nonce: tpfMediaOrganizer.nonce,
                    name: name,
                    parent_id: parentId || 0
                },
                success: function(response) {
                    if (response.success) {
                        self.showToast(response.data.message, 'success');
                        // Navigate to All Media view
                        var url = new URL(window.location.href);
                        url.searchParams.delete('tpf_media_folder');
                        window.location.href = url.toString();
                    } else {
                        self.showToast(response.data.message, 'error');
                    }
                },
                error: function() {
                    self.showToast(self.strings.errorOccurred, 'error');
                }
            });
        },

        /**
         * Start rename mode
         */
        startRename: function(folderId) {
            var $item = $('.tpf-mo-folder-item[data-folder-id="' + folderId + '"]');
            var $link = $item.find('> .tpf-mo-folder-link');
            var $name = $link.find('.tpf-mo-folder-name');
            var currentName = $name.text();

            $name.html('<input type="text" class="tpf-mo-rename-input" value="' + this.escapeHtml(currentName) + '">');
            $link.find('.tpf-mo-rename-input').focus().select();

            var self = this;

            $link.find('.tpf-mo-rename-input').on('keypress', function(e) {
                if (e.which === 13) {
                    var newName = $(this).val().trim();
                    if (newName && newName !== currentName) {
                        self.renameFolder(folderId, newName);
                    } else {
                        self.cancelRename();
                    }
                }
            }).on('blur', function() {
                var newName = $(this).val().trim();
                if (newName && newName !== currentName) {
                    self.renameFolder(folderId, newName);
                } else {
                    self.cancelRename();
                }
            });
        },

        /**
         * Cancel rename mode
         */
        cancelRename: function() {
            var $input = $('.tpf-mo-rename-input');
            if ($input.length) {
                var $name = $input.closest('.tpf-mo-folder-name');
                $name.text($input.val());
            }
        },

        /**
         * Rename folder
         */
        renameFolder: function(folderId, newName) {
            var self = this;

            $.ajax({
                url: tpfMediaOrganizer.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'tpf_mo_rename_folder',
                    nonce: tpfMediaOrganizer.nonce,
                    folder_id: folderId,
                    name: newName
                },
                success: function(response) {
                    if (response.success) {
                        self.showToast(response.data.message, 'success');
                        window.location.reload();
                    } else {
                        self.showToast(response.data.message, 'error');
                        self.cancelRename();
                    }
                },
                error: function() {
                    self.showToast(self.strings.errorOccurred, 'error');
                    self.cancelRename();
                }
            });
        },

        /**
         * Delete folder
         */
        deleteFolder: function(folderId) {
            if (!confirm(this.strings.confirmDelete)) {
                return;
            }

            var self = this;

            $.ajax({
                url: tpfMediaOrganizer.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'tpf_mo_delete_folder',
                    nonce: tpfMediaOrganizer.nonce,
                    folder_id: folderId
                },
                success: function(response) {
                    if (response.success) {
                        self.showToast(response.data.message, 'success');

                        // If we were viewing this folder, go to all media
                        if (self.currentFolder == folderId) {
                            self.currentFolder = 'all';
                        }

                        window.location.reload();
                    } else {
                        self.showToast(response.data.message, 'error');
                    }
                },
                error: function() {
                    self.showToast(self.strings.errorOccurred, 'error');
                }
            });
        },

        /**
         * Assign attachments to folder
         */
        assignToFolder: function(attachmentIds, folderId) {
            var self = this;

            $.ajax({
                url: tpfMediaOrganizer.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'tpf_mo_assign_folder',
                    nonce: tpfMediaOrganizer.nonce,
                    attachment_ids: attachmentIds,
                    folder_id: folderId
                },
                success: function(response) {
                    if (response.success) {
                        self.showToast(response.data.message, 'success');
                        // Navigate to All Media view (filtering on initial load is unreliable)
                        var url = new URL(window.location.href);
                        url.searchParams.delete('tpf_media_folder');
                        window.location.href = url.toString();
                    } else {
                        self.showToast(response.data.message, 'error');
                    }
                },
                error: function() {
                    self.showToast(self.strings.errorOccurred, 'error');
                }
            });
        },

        /**
         * Show context menu
         */
        showContextMenu: function(x, y, folderId) {
            var $menu = $('.tpf-mo-context-menu');
            $menu.data('folder-id', folderId);
            $menu.css({
                left: x,
                top: y
            }).addClass('active');
        },

        /**
         * Hide context menu
         */
        hideContextMenu: function() {
            $('.tpf-mo-context-menu').removeClass('active');
        },

        /**
         * Show subfolder form
         */
        showSubfolderForm: function(parentId) {
            var name = prompt(this.strings.folderName);
            if (name && name.trim()) {
                this.createFolder(name.trim(), parentId);
            }
        },

        /**
         * Show toast notification
         */
        showToast: function(message, type) {
            var $toast = $('.tpf-mo-toast');
            $toast.removeClass('success error').addClass(type).text(message).addClass('active');

            setTimeout(function() {
                $toast.removeClass('active');
            }, 3000);
        },

        /**
         * Escape HTML
         */
        escapeHtml: function(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        // Only init on upload.php
        if ($('body').hasClass('upload-php')) {
            TPF_MediaOrganizer.init();
        }
    });

})(jQuery);
