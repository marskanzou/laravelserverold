@extends('layouts.main')
@section('content')
<h2>WhatsApp QR</h2>
<div id="qrCode"></div>
<button onclick="resetSession()">Reset Session</button>

<h2>Send Message</h2>
<form id="sendForm">
    @csrf
    <input type="text" name="phone" placeholder="Phone" required>
    <textarea name="message" placeholder="Message" required></textarea>
    <button type="submit">Send</button>
</form>

<div id="status"></div>
<div id="logs"></div>

<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script>
const token = '{{ env("API_TOKEN") }}';

async function fetchQR(){
    try{
        const res = await axios.get('{{ route("whatsapp.qr") }}');
        document.getElementById('qrCode').innerHTML='<pre>'+res.data.qr+'</pre>';
    }catch{ document.getElementById('qrCode').innerText='QR not ready'; }
}
fetchQR();
setInterval(fetchQR,5000);

function resetSession(){
    axios.post('{{ route("whatsapp.reset") }}').then(()=> alert('Session reset!'));
}

document.getElementById('sendForm').addEventListener('submit', async e=>{
    e.preventDefault();
    const phone=e.target.phone.value;
    const message=e.target.message.value;
    try{
        await axios.post('http://localhost:3000/admin/send',{ phone,message },{ headers:{ Authorization:'Bearer '+token } });
        alert('Sent!');
    }catch(e){ alert(e.response?.data?.message || 'Error'); }
});
</script>
@endsection

