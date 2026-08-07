<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:24px 0 8px 0;border-collapse:separate;border-spacing:0;">
    @foreach ($rows as $index => $row)
        <tr>
            <td style="padding:14px 16px;border:1px solid #d9e8e6;border-bottom:0;background-color:{{ $index % 2 === 0 ? '#f7fbfc' : '#ffffff' }};font-size:14px;font-weight:700;color:#113d34;">
                {{ $row['label'] }}
            </td>
            <td style="padding:14px 16px;border:1px solid #d9e8e6;border-left:0;border-bottom:0;background-color:{{ $index % 2 === 0 ? '#f7fbfc' : '#ffffff' }};font-size:14px;color:#143b33;text-align:right;">
                {{ $row['value'] }}
            </td>
        </tr>
    @endforeach
</table>
