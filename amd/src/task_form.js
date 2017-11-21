/**
 * @module tool_etl/task_form
 */
define(['jquery'], function($) {
    var taskForm = {
        /**
         * Initialisation method called by php js_call_amd()
         */
        init: function() {

            $('#fitem_id_updateform').hide();

            $('#id_source').on('change', function() {
                taskForm.preventSubmit();
                taskForm.updateForm();
            });

            $('#id_target').on('change', function() {
                taskForm.preventSubmit();
                taskForm.updateForm();
            });

            $('#id_processor').on('change', function() {
                taskForm.preventSubmit();
                taskForm.updateForm();
            });
        },

        /**
         * Update form
         */
        updateForm: function() {
            $('#id_updateform').click();
        },

        preventSubmit: function() {
            $('#id_submitbutton').attr('disabled','disabled');
            $('#id_cancel').attr('disabled','disabled');
        }
    };

    return taskForm;
});
