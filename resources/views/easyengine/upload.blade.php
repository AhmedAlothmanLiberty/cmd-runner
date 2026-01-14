<!doctype html>
<html>
<head><meta charset="utf-8"><title>EasyEngine Upload</title></head>
<body style="font-family: Arial; max-width: 720px; margin: 30px auto;">
  <h2>EasyEngine Upload</h2>

  @if(session('ok')) <p style="color:green">{{ session('ok') }}</p> @endif
  @if(session('error')) <p style="color:red">{{ session('error') }}</p> @endif
  @if($errors->any())
    <ul style="color:red">
      @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
    </ul>
  @endif

  <form method="post" enctype="multipart/form-data" action="{{ route('ee.upload') }}">
    @csrf

    <div style="margin: 10px 0;">
      <label>State</label><br>
      <input name="state" value="{{ old('state','CA') }}" required>
    </div>

    <div style="margin: 10px 0;">
      <label>Drop date</label><br>
      <input type="date" name="drop_date" value="{{ old('drop_date', now()->toDateString()) }}" required>
    </div>

    <div style="margin: 10px 0;">
      <label>CSV file</label><br>
      <input type="file" name="file" required>
    </div>

    <button type="submit">Upload</button>
  </form>
</body>
</html>
