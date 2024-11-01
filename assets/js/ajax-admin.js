jQuery(document).ready(function($) {

    $('a#checkPhoneBTN').on('click', function(){
        let phone = $('#spillt_author_phone').val();

        if(phone.length === 0){
            alert("Phone can't be blank." );
        } else {
            let data = {
                action: 'spillt_send_sms_to_phone',
                phone: phone
            };
            $.ajax({
                url : ajax_object.ajax_url,
                data : data,
                type : 'POST',
                dataType : 'json',
                beforeSend : function () {
                    $('#spillt_verify_phone_wrap .verify-error').empty();
                    $('#phoneToVerify').val(phone);
                }
            })
            .done(function(response) {
                if(response.status == 'error'){
                    $('#phone_check_error').html('<p class="notice error notice-error">'+response.message+'</p>')
                } else {
                    $('#spillt_verify_phone_wrap').addClass('show');
                }
            })
            .fail(function(jqXHR, textStatus, errorThrown) {
                console.error("Request failed: " + textStatus + ", " + errorThrown);
            });
        }
    });
    $('#spillt_verify_phone').submit(function(){
        let data = $( this ).serialize();
        $.ajax({
            url : ajax_object.ajax_url,
            data : data,
            type : 'POST',
            dataType : 'json',
            beforeSend : function ( xhr ) {
                $('#spillt_verify_phone_wrap').addClass('show');
            },
        })
        .done( function( response ) {
            if (response.status == 'error'){
                $('#spillt_verify_phone_wrap .verify-error').html('<p class="notice error notice-error">'+response.message+'</p>');
                window.setTimeout(function() {
                    $('#spilt_resend_sms').addClass('show');
                },1000);
            }
            if (response.status == 'success'){
                $('#spillt_blog_phone').submit();
                $('#spillt_verify_phone_wrap').removeClass('show');
            }
        });
        $("#spillt_verify_phone").unbind('submit');
        return false;
    });
    $('a#spilt_resend_sms').on('click', function(){
        let phone = $('#phoneToVerify').val();

        let data = {
            action: 'spillt_send_sms_to_phone',
            phone: phone
        };
        $.ajax({
            url : ajax_object.ajax_url,
            data : data,
            type : 'POST',
            dataType : 'json',
            beforeSend : function ( xhr ) {
                $('#spillt_verify_phone_wrap .verify-error').empty();
            },
        })
        .done(function( response ){
            if(jQuery.isEmptyObject(response)){} else {
                $('#spillt_verify_phone_wrap .verify-error').html('<p class="notice error notice-error">'+response.msg+'</p>')
            }
        });
    });
    $( document ).ajaxComplete(function() {
        $('#close-manually-sync').on('click', function(){
            $('#manual_sync_result_wrap').removeClass('show');
            location.reload();
        });
    });
});
