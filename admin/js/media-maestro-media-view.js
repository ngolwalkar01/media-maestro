/**
 * Media Maestro - Media View Integration
 * 
 * Extends the WordPress Media Library modal to add AI Tools.
 */
(function ($, _) {
    console.log('Media Maestro Media View Loaded (Fixed)');

    var media = wp.media;

    if (typeof mm_data === 'undefined') {
        console.log('Media Maestro: mm_data missing in Media View');
        return;
    }
    console.log('Media Maestro Media View Data present for ID:', mm_data.attachment_id || 'dynamic');

    /**
     * View for the AI Tools Sidebar Section
     */
    media.view.MediaMaestroSidebar = media.View.extend({
        className: 'mm-sidebar-section',
        template: wp.template('mm-sidebar-template'),

        events: {
            'change .mm-operation-select': 'onOperationChange',
            'click .mm-btn-run': 'runJob'
        },

        initialize: function (options) {
            console.log('MediaMaestroSidebar initialized');
            media.View.prototype.initialize.apply(this, arguments);
            this.model.on('change', this.render, this);
        },

        prepare: function () {
            var data = this.model.toJSON();
            return data;
        },

        render: function () {
            this.$el.html(this.template(this.model.toJSON()));
            this.onOperationChange(); // Set initial state
            return this;
        },

        onOperationChange: function () {
            var op = this.$('.mm-operation-select').val();

            // Show/Hide Strength
            // Some operations need strength
            if (['style_transfer', 'sketch', 'structure', 'generate_sd3'].includes(op)) {
                this.$('.mm-strength-label').show();
            } else {
                this.$('.mm-strength-label').hide();
            }

            // Show/Hide Prompt
            // Remove BG and Upscale Fast usually don't need prompt
            if (['remove_bg', 'upscale_fast', 'upscale_conservative'].includes(op)) {
                this.$('.mm-prompt-label').hide();
            } else {
                this.$('.mm-prompt-label').show();
            }
            // Show/Hide Direction
            if (op === 'outpaint') {
                this.$('.mm-direction-label').show();
            } else {
                this.$('.mm-direction-label').hide();
            }
        },

        runJob: function (e) {
            e.preventDefault();
            var op = this.$('.mm-operation-select').val();
            var prompt = this.$('.mm-prompt-input').val();
            var strength = this.$('.mm-strength-input').val();
            var direction = this.$('.mm-direction-select').val();

            // Validation
            if (!['remove_bg', 'upscale_fast', 'upscale_conservative', 'erase'].includes(op) && !prompt) {
                alert('Please enter a prompt for this operation.');
                return;
            }

            console.log('Running Job:', op, prompt, strength, direction);

            this.startJob(op, {
                prompt: prompt,
                strength: strength,
                direction: direction
            });
        },

        startJob: function (operation, params = {}) {
            var self = this;
            var attachmentId = this.model.get('id');
            var $status = this.$('.mm-status');

            $status.text('Starting ' + operation + '...');

            $.ajax({
                url: mm_data.api_url,
                method: 'POST',
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', mm_data.nonce);
                },
                data: {
                    attachment_id: attachmentId,
                    operation: operation,
                    params: params
                }
            }).done(function (response) {
                if (response.id) {
                    $status.text('Job ' + response.id + ' started...');
                    self.pollJob(response.id);
                } else {
                    $status.text('Failed to start.');
                }
            }).fail(function () {
                $status.text('Request failed.');
            });
        },

        pollJob: function (jobId) {
            var self = this;
            var $status = this.$('.mm-status');

            setTimeout(function () {
                $.ajax({
                    url: mm_data.api_url + '/' + jobId,
                    method: 'GET',
                    beforeSend: function (xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', mm_data.nonce);
                    }
                }).done(function (response) {
                    if (response.status === 'completed') {
                        $status.html('Done! <br>New image created.');
                        // Trigger a change to refresh
                        self.model.fetch();
                    } else if (response.status === 'failed') {
                        $status.text('Failed: ' + response.error);
                    } else {
                        $status.text(response.status + '...');
                        self.pollJob(jobId);
                    }
                });
            }, 2000);
        }
    });

    /**
     * Extend the Attachment Details hook to inject our sidebar
     */
    var originalAttachmentDetails = media.view.Attachment.Details;
    media.view.Attachment.Details = originalAttachmentDetails.extend({
        initialize: function () {
            originalAttachmentDetails.prototype.initialize.apply(this, arguments);
            this.on('ready', this.renderMediaMaestro, this);
        },

        renderMediaMaestro: function () {
            if (this.model.get('type') !== 'image') {
                return;
            }

            var sidebarView = new media.view.MediaMaestroSidebar({
                controller: this.controller,
                model: this.model,
                priority: 200
            });

            // Manual append to ensure visibility
            // Try to place it before the standard settings (Title, Caption, etc.)
            var $settings = this.$('.settings');
            if ($settings.length) {
                $settings.before(sidebarView.el);
                console.log('Inserted sidebar before .settings');
            } else {
                // Fallback to compat or main
                var $compat = this.$('.compat-attachment-fields');
                if ($compat.length) {
                    $compat.prepend(sidebarView.el);
                    console.log('Appended sidebar to .compat-attachment-fields');
                } else {
                    this.$el.prepend(sidebarView.el); // Prepend to main el to be at top
                    console.log('Prepended sidebar to main el');
                }
            }

            sidebarView.render();
        }
    });

})(jQuery, _);
