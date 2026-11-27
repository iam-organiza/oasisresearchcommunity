<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<title>{{ config('app.name') }}</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
{{-- Mail apps that load web fonts (Apple Mail, iOS) get the site's typeface; the rest fall back to Helvetica/Arial. --}}
<link href="https://fonts.googleapis.com/css2?family=Schibsted+Grotesk:wght@400;600;700&display=swap" rel="stylesheet">
<style>
@media only screen and (max-width: 640px) {
.inner-body,
.footer {
width: 100% !important;
}

.wrapper {
padding: 16px 8px !important;
}

.header-cell,
.content-cell {
padding-left: 24px !important;
padding-right: 24px !important;
}
}

@media only screen and (max-width: 500px) {
.status-cell {
display: block !important;
width: 100% !important;
padding-bottom: 4px !important;
}

.button {
display: block !important;
text-align: center !important;
}
}
</style>
{!! $head ?? '' !!}
</head>
<body>

{{-- Letterhead: a navy band with the white logo, the message on a white sheet, and the secretariat's details on the paper tone below. --}}
<table class="wrapper" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="center">
<table class="content" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="center">
<table class="inner-body" align="center" width="600" cellpadding="0" cellspacing="0" role="presentation">
{!! $header ?? '' !!}

<!-- Email Body -->
<tr>
<td class="content-cell">
{!! Illuminate\Mail\Markdown::parse($slot) !!}

{!! $subcopy ?? '' !!}
</td>
</tr>
</table>
</td>
</tr>

{!! $footer ?? '' !!}
</table>
</td>
</tr>
</table>
</body>
</html>
