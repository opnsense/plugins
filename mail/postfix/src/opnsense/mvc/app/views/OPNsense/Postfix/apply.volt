/**
 * Reconfigure
 */
$("#reconfigureAct").click(function(){
    $("#reconfigureAct_progress").addClass("fa fa-spinner fa-pulse");
    ajaxCall("/api/postfix/service/reconfigure", {}, function(data,status) {
        // when done, disable progress animation.
        $("#reconfigureAct_progress").removeClass("fa fa-spinner fa-pulse");
            if (status != "success" || data['status'] != 'ok') {
            BootstrapDialog.show({
                type: BootstrapDialog.TYPE_WARNING,
                title: "{{ lang._('Error reconfiguring Postfix') }}",
                message: data['status'],
                draggable: true
            });
        } else {
            ajaxCall("/api/postfix/service/reconfigure", {});
        }
    });
});
