<!DOCTYPE html>
<html>
    <head>
        <style>
            @page {
                size: A4 portrait;
                margin: 6mm;
            }

            body {
                font-family: Arial, Helvetica, sans-serif;
                margin: 0;
                padding: 0;
            }

            .page {
                width: 198mm;
                height: 285mm;
                padding-top: 6mm;
                box-sizing: border-box;
            }

            table.grid {
                width: 190mm;
                margin: 0 auto;
                border-collapse: separate;
                border-spacing: 2mm;
                table-layout: fixed;
            }

            td.label-cell {
                border: 1px solid #000;
                width: 45mm;
                height: 20mm;
                padding: 1.0mm;
                box-sizing: border-box;
                vertical-align: center;
                text-align: center;
                overflow: hidden;
            }

            .product-name {
                font-size: 7px;
                font-weight: bold;
                line-height: 1.1;
                margin-bottom: 0.5mm;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .meta {
                font-size: 6px;
                margin-bottom: 0.4mm;
            }

            .meta span {
                display: block;
                line-height: 1.1;
            }

            .barcode {
                margin: 0;
                padding: 0;
            }

            .barcode img {
                display: block;
                width: 100%;
                max-width: 100%;
                height: 7mm;
                margin: 0 auto;
                image-rendering: crisp-edges;
            }

            .barcode-text {
                font-size: 5px;
                letter-spacing: 0.2px;
                margin-top: 0.2mm;
                font-family: "Courier New", Courier, monospace;
            }

            .company-name {
                font-size: 6px;
                font-weight: bold;
                line-height: 1.1;
                margin-top: 0.4mm;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
        </style>
    </head>
    <body>
        <div class="page">
            <table class="grid">
                @for ($row = 0; $row < 11; $row++)
                    <tr>
                        @for ($col = 0; $col < 6; $col++)
                            <td class="label-cell">
                                <div class="product-name">
                                    {{ $batch->product->name }}
                                </div>

                                <div class="meta">
                                    <span><strong>Batch:</strong> {{ $batch->batch_code }}</span>
                                    @if($batch->expires_at)
                                        <span><strong>Exp:</strong> {{ $batch->expires_at->format('Y-m-d') }}</span>
                                    @endif
                                </div>

                                <div class="barcode">
                                    <img src="data:image/png;base64,{{ $barcode }}" alt="Barcode">
                                    <div class="barcode-text">
                                        {{ $batch->barcode->code }}
                                    </div>
                                </div>

                                <div class="company-name">
                                    Biyinzika Poultry
                                </div>

                            </td>
                        @endfor
                    </tr>
                @endfor
            </table>
        </div>
    </body>
</html>
