import Foundation
import UIKit
import AVFoundation

/// Opens a native barcode/QR scanner using AVFoundation.
enum ScannerFunctions {

    class Scan: NSObject, BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            let prompt = (parameters["prompt"] as? String) ?? "Scan barcode"
            let continuous = (parameters["continuous"] as? Bool)
                ?? ((parameters["continuous"] as? NSNumber)?.boolValue ?? false)
            let formats = parameters["formats"] as? [String] ?? ["qr"]
            let sessionId = parameters["id"] as? String

            DispatchQueue.main.async {
                guard let top = Self.topViewController() else {
                    print("Scanner.Scan: no view controller to present")
                    return
                }

                let status = AVCaptureDevice.authorizationStatus(for: .video)
                let present = {
                    let vc = BarcodeScannerViewController(
                        prompt: prompt,
                        continuous: continuous,
                        formats: formats,
                        sessionId: sessionId
                    )
                    vc.modalPresentationStyle = .fullScreen
                    top.present(vc, animated: true)
                }

                switch status {
                case .authorized:
                    present()
                case .notDetermined:
                    AVCaptureDevice.requestAccess(for: .video) { granted in
                        DispatchQueue.main.async {
                            if granted {
                                present()
                            } else {
                                print("Scanner.Scan: camera permission denied")
                            }
                        }
                    }
                default:
                    print("Scanner.Scan: camera permission not granted")
                }
            }

            // Async UI — return immediately so Livewire never hangs
            return ["success": true, "opened": true]
        }

        private static func topViewController() -> UIViewController? {
            let scenes = UIApplication.shared.connectedScenes.compactMap { $0 as? UIWindowScene }
            let window = scenes.flatMap(\.windows).first(where: \.isKeyWindow)
                ?? scenes.flatMap(\.windows).first
            var top = window?.rootViewController
            while let presented = top?.presentedViewController {
                top = presented
            }
            return top
        }
    }
}

// MARK: - Scanner UI

final class BarcodeScannerViewController: UIViewController, AVCaptureMetadataOutputObjectsDelegate {
    private let promptText: String
    private let continuous: Bool
    private let formats: [String]
    private let sessionId: String?

    private let session = AVCaptureSession()
    private var previewLayer: AVCaptureVideoPreviewLayer?
    private var hasEmitted = false
    private let feedback = UINotificationFeedbackGenerator()

    init(prompt: String, continuous: Bool, formats: [String], sessionId: String?) {
        self.promptText = prompt
        self.continuous = continuous
        self.formats = formats
        self.sessionId = sessionId
        super.init(nibName: nil, bundle: nil)
    }

    required init?(coder: NSCoder) {
        fatalError("init(coder:) has not been implemented")
    }

    override func viewDidLoad() {
        super.viewDidLoad()
        view.backgroundColor = .black
        setupCamera()
        setupChrome()
        feedback.prepare()
    }

    override func viewWillAppear(_ animated: Bool) {
        super.viewWillAppear(animated)
        DispatchQueue.global(qos: .userInitiated).async { [weak self] in
            self?.session.startRunning()
        }
    }

    override func viewWillDisappear(_ animated: Bool) {
        super.viewWillDisappear(animated)
        session.stopRunning()
    }

    override func viewDidLayoutSubviews() {
        super.viewDidLayoutSubviews()
        previewLayer?.frame = view.bounds
    }

    private func setupChrome() {
        let prompt = UILabel()
        prompt.text = promptText
        prompt.textColor = .white
        prompt.font = .boldSystemFont(ofSize: 16)
        prompt.textAlignment = .center
        prompt.numberOfLines = 0
        prompt.translatesAutoresizingMaskIntoConstraints = false
        view.addSubview(prompt)

        let close = UIButton(type: .system)
        close.setTitle("Close", for: .normal)
        close.setTitleColor(.white, for: .normal)
        close.titleLabel?.font = .boldSystemFont(ofSize: 16)
        close.backgroundColor = UIColor.white.withAlphaComponent(0.2)
        close.layer.cornerRadius = 12
        close.translatesAutoresizingMaskIntoConstraints = false
        close.addTarget(self, action: #selector(closeTapped), for: .touchUpInside)
        view.addSubview(close)

        NSLayoutConstraint.activate([
            prompt.topAnchor.constraint(equalTo: view.safeAreaLayoutGuide.topAnchor, constant: 24),
            prompt.leadingAnchor.constraint(equalTo: view.leadingAnchor, constant: 24),
            prompt.trailingAnchor.constraint(equalTo: view.trailingAnchor, constant: -24),
            close.bottomAnchor.constraint(equalTo: view.safeAreaLayoutGuide.bottomAnchor, constant: -24),
            close.centerXAnchor.constraint(equalTo: view.centerXAnchor),
            close.widthAnchor.constraint(equalToConstant: 120),
            close.heightAnchor.constraint(equalToConstant: 48),
        ])
    }

    private func setupCamera() {
        guard let device = AVCaptureDevice.default(for: .video),
              let input = try? AVCaptureDeviceInput(device: device),
              session.canAddInput(input) else {
            return
        }

        session.beginConfiguration()
        session.addInput(input)

        let output = AVCaptureMetadataOutput()
        if session.canAddOutput(output) {
            session.addOutput(output)
            output.setMetadataObjectsDelegate(self, queue: DispatchQueue.main)
            output.metadataObjectTypes = mapFormats(formats)
        }
        session.commitConfiguration()

        let preview = AVCaptureVideoPreviewLayer(session: session)
        preview.videoGravity = .resizeAspectFill
        preview.frame = view.bounds
        view.layer.insertSublayer(preview, at: 0)
        previewLayer = preview
    }

    private func mapFormats(_ formats: [String]) -> [AVMetadataObject.ObjectType] {
        if formats.contains("all") {
            return [
                .qr, .ean13, .ean8, .code128, .code39, .upce, .pdf417, .aztec, .dataMatrix, .interleaved2of5,
            ]
        }

        var types: [AVMetadataObject.ObjectType] = []
        for f in formats {
            switch f.lowercased() {
            case "qr": types.append(.qr)
            case "ean13": types.append(.ean13)
            case "ean8": types.append(.ean8)
            case "code128": types.append(.code128)
            case "code39": types.append(.code39)
            case "upca", "upce": types.append(.upce)
            default: break
            }
        }
        return types.isEmpty ? [.qr] : types
    }

    func metadataOutput(
        _ output: AVCaptureMetadataOutput,
        didOutput metadataObjects: [AVMetadataObject],
        from connection: AVCaptureConnection
    ) {
        guard let object = metadataObjects.first as? AVMetadataMachineReadableCodeObject,
              let value = object.stringValue, !value.isEmpty else {
            return
        }

        if !continuous, hasEmitted { return }
        hasEmitted = true

        feedback.notificationOccurred(.success)
        emitScan(data: value, format: formatName(object.type))

        if !continuous {
            session.stopRunning()
            dismiss(animated: true)
        } else {
            // brief cooldown to avoid spamming the same code
            DispatchQueue.main.asyncAfter(deadline: .now() + 1.2) { [weak self] in
                self?.hasEmitted = false
            }
        }
    }

    private func formatName(_ type: AVMetadataObject.ObjectType) -> String {
        switch type {
        case .qr: return "qr"
        case .ean13: return "ean13"
        case .ean8: return "ean8"
        case .code128: return "code128"
        case .code39: return "code39"
        case .upce: return "upce"
        default: return type.rawValue
        }
    }

    private func emitScan(data: String, format: String) {
        var payload: [String: Any?] = [
            "data": data,
            "format": format,
        ]
        if let sessionId {
            payload["id"] = sessionId
        }

        // Match official plugin event class so #[OnNative(CodeScanned::class)] works
        LaravelBridge.shared.send?(
            "Native\\Mobile\\Events\\Scanner\\CodeScanned",
            payload
        )
        print("Scanner: CodeScanned data=\(data) format=\(format)")
    }

    @objc private func closeTapped() {
        session.stopRunning()
        dismiss(animated: true)
    }
}
