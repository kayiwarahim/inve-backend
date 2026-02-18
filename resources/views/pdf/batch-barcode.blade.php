<!DOCTYPE html>
<html>
<head>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            margin: 0;
            padding: 0;
        }

        .label {
            width: 300px;
            border: 2px solid #000;
            padding: 12px;
            box-sizing: border-box;
            text-align: center;
        }

        .product-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 4px;
        }

        .meta {
            font-size: 12px;
            margin-bottom: 6px;
        }

        .meta span {
            display: block;
        }

        .barcode {
            margin: 10px 0;
        }

        .barcode img {
            width: 100%;
            height: auto;
        }

        .barcode-text {
            font-size: 11px;
            letter-spacing: 1px;
            margin-top: 4px;
        }

        .footer {
            font-size: 10px;
            margin-top: 6px;
            color: #333;
        }
    </style>
</head>
<body>

<div class="label">
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

    <div class="footer">
        Packed & Verified
    </div>
</div>

</body>
</html>
