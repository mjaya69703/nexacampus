// Shim minimal untuk Barcode Detection API (belum ada di lib.dom TypeScript).
// Dipakai halaman absensi mahasiswa untuk memindai QR dosen lewat kamera.

type BarcodeDetectorOptions = {
    formats?: string[];
};

type DetectedBarcode = {
    rawValue: string;
    format: string;
    boundingBox?: DOMRectReadOnly;
    cornerPoints?: Array<{ x: number; y: number }>;
};

declare class BarcodeDetector {
    constructor(options?: BarcodeDetectorOptions);
    static getSupportedFormats(): Promise<string[]>;
    detect(source: ImageBitmapSource): Promise<DetectedBarcode[]>;
}

interface Window {
    BarcodeDetector?: typeof BarcodeDetector;
}
