<!doctype html>
<html>
<head><meta charset="utf-8"><title>EasyEngine Upload</title></head>
<body style="font-family: Arial; max-width: 720px; margin: 30px auto;">
  <h2>EasyEngine Upload</h2>

  <div style="background:#f6f8fb;border:1px solid #d8e0ea;padding:12px 14px;margin:14px 0;border-radius:6px;">
    <div><strong>App target:</strong> {{ $uploadConfig['app_max_label'] }}</div>
    <div><strong>PHP upload_max_filesize:</strong> {{ $uploadConfig['php_upload_max_label'] }}</div>
    <div><strong>PHP post_max_size:</strong> {{ $uploadConfig['php_post_max_label'] }}</div>
    <div><strong>Effective request limit:</strong> {{ $uploadConfig['effective_label'] }}</div>
    @if (! $uploadConfig['supports_target_upload'])
      <p style="color:#b42318;margin:10px 0 0;">
        This server is not currently configured for {{ $uploadConfig['app_max_label'] }} uploads. Raise PHP
        <code>upload_max_filesize</code>, PHP <code>post_max_size</code>, and the web server body-size limit
        before trying a 1 GB file.
      </p>
    @endif
  </div>

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
      <input type="file" name="file" accept=".csv,.txt,text/csv,text/plain" required>
    </div>

    <button type="submit">Upload</button>
  </form>
</body>
</html>
