$(document).on('submit', '#sign_all_modal', function (e) {
    e.preventDefault();
    const password = $('#password').val();
    const amendment = $('#amendment').val();
    const user_id = $('#user_id').val();
    const csrf = $('meta[name="csrf-token"]').attr('content') || $('#csrf_token_form').val() || '';

    $.ajax({
        url: 'signAll.php',
        method: 'POST',
        data: {
            password: password,
            amendment: amendment,
            user_id: user_id,
            csrf_token_form: csrf
        },
        success: function (data) {
            if (data) {
                let items = (typeof data === 'object') ? data : JSON.parse(data);
                if (items.status === 'success') {
                    $('#mySignModal').modal('hide');
                    $('#password').val('');
                    $('#amendment').val('');
                    $('#errorMessage').text('');
                    $('#mymaintable').load(location.href + ' #mymaintable>*', '');
                } else {
                    $('#errorMessage').text(items.message);
                }
            } else {
                alert('Error: ' + data);
            }
        },
        error: function (xhr, status, error) {
            console.error('AJAX Error:', status, error);
        }
    });
});

$('#mySignModal').on('hidden.bs.modal', function () {
    $('#password').val('');
    $('#amendment').val('');
    $('#errorMessage').text('');
});
