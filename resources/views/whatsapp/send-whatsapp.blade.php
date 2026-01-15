@extends('layouts.main')
@section('title', 'Send WhatsApp')

@section('content')
<div class="container">
    <h2>Send WhatsApp Message</h2>
    <div id="whatsappStatus" class="mb-3"></div>

    <form id="whatsappForm">
        @csrf
        <input type="text" name="phone" placeholder="رقم الهاتف" class="form-control mb-2" required>
        <textarea name="message" placeholder="الرسالة" class="form-control mb-2" required></textarea>
        <button type="submit" class="btn btn-primary">أرسل</button>
    </form>

    <h3 class="mt-4">Message Log</h3>
    <table class="table table-bordered">
        <thead>
            <tr><th>رقم</th><th>الرسالة</th><th>الوقت</th><th>الحالة</th></tr>
        </thead>
        <tbody id="logTable"></tbody>
    </table>
</div>
@endsection

@section('js')
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script>
const token = '{{ env("API_TOKEN") }}';

// Fetch status every 5s
async function fetchStatus(){
    try {
        const res = await axios.get('http://localhost:3000/admin/status', { headers:{ Authorization:'Bearer '+token } });
        $('#whatsappStatus').html(`WhatsApp: ${res.data.ready ? '✅' : '❌'} | Queue: ${res.data.queueLength}`);
    } catch { $('#whatsappStatus').html('خطأ في الاتصال بـWhatsApp'); }
}
fetchStatus(); setInterval(fetchStatus,5000);

// Send message
$('#whatsappForm').on('submit', async function(e){
    e.preventDefault();
    const phone = $(this).find('input[name="phone"]').val();
    const message = $(this).find('textarea[name="message"]').val();
    try {
        await axios.post('http://localhost:3000/admin/send', { phone, message }, { headers:{ Authorization:'Bearer '+token } });
        fetchLogs(); $(this).trigger('reset');
    } catch (err) { alert(err.response?.data?.message || 'حدث خطأ'); }
});

// Fetch message logs
async function fetchLogs(){
    try {
        const res = await axios.get('http://localhost:3000/admin/logs', { headers:{ Authorization:'Bearer '+token } });
        const tbody = $('#logTable'); tbody.html('');
        res.data.reverse().forEach(l=>{
            tbody.append(`<tr><td>${l.number}</td><td>${l.text}</td><td>${l.time}</td><td>${l.status}</td></tr>`);
        });
    } catch {}
}
fetchLogs(); setInterval(fetchLogs,5000);
</script>
@endsection

