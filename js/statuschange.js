$(document).on('click', '.documentstatus', function () {
    const enc = $(this).attr('data-encounter');
    const status = $(this).attr('data-status');
    const id = $(this).attr('data-id');
    const csrf = $('meta[name="csrf-token"]').attr('content') || '';
    $.post('statuschange.php', {
        encounter: enc,
        status: status,
        id: id,
        csrf_token_form: csrf
    }, function (data) {
        if (data) {
            alert('Status changed successfully');
            $('#mymaintable').load(location.href + ' #mymaintable>*', '');
        } else {
            alert('Error: ' + data);
        }
    });
});
