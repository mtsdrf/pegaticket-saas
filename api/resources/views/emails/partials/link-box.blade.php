<div style="margin-top:18px;">
    <div style="font-size:13px;line-height:1.5;color:#6f8581;margin-bottom:8px;">
        {{ $label ?? 'Se preferir, copie e cole este link no navegador:' }}
    </div>
    <div style="padding:14px 16px;border-radius:14px;background-color:#f2f8f8;border:1px solid #d9e8e6;word-break:break-all;">
        <a href="{{ $url }}" style="color:#08cfa7;text-decoration:none;font-size:14px;line-height:1.5;">
            {{ $url }}
        </a>
    </div>
</div>
