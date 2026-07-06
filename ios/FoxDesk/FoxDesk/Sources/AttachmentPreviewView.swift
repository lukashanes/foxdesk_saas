import QuickLook
import SwiftUI
import UIKit
import FoxDeskKit

struct AttachmentPreviewView: View {
    @Environment(AppSession.self) private var session

    let attachment: TicketAttachment

    @State private var state: PreviewState = .loading

    var body: some View {
        Group {
            switch state {
            case .loading:
                ProgressView("Loading attachment")
                    .frame(maxWidth: .infinity, maxHeight: .infinity)
            case .image(let image):
                ScrollView([.horizontal, .vertical]) {
                    Image(uiImage: image)
                        .resizable()
                        .scaledToFit()
                        .padding()
                }
                .frame(maxWidth: .infinity, maxHeight: .infinity)
                .background(Color(.systemBackground))
            case .file(let url):
                QuickLookPreview(url: url)
                    .ignoresSafeArea(edges: .bottom)
            case .failed(let message):
                ContentUnavailableView("Could not open attachment", systemImage: "paperclip.badge.exclamationmark", description: Text(message))
            }
        }
        .navigationTitle(attachment.filename?.isEmpty == false ? attachment.filename ?? "Attachment" : "Attachment")
        .navigationBarTitleDisplayMode(.inline)
        .task(id: attachment.id) {
            await loadPreview()
        }
    }

    private func loadPreview() async {
        state = .loading

        do {
            let preview = try await session.authenticated { accessToken in
                let resolved = try await resolvedAttachment(accessToken: accessToken)
                let urlString = resolved.canPreview == true
                    ? (resolved.previewUrl ?? resolved.downloadUrl)
                    : (resolved.downloadUrl ?? resolved.previewUrl)

                guard let url = session.client.resourceURL(from: urlString) else {
                    throw FoxDeskAPIError.server(
                        statusCode: 404,
                        message: "This attachment does not have an available preview."
                    )
                }

                let data = try await session.client.downloadResource(accessToken: accessToken, url: url)
                return (resolved, data)
            }

            if let image = UIImage(data: preview.1), isImage(preview.0) {
                state = .image(image)
                return
            }

            let fileURL = try writeTemporaryFile(data: preview.1, attachment: preview.0)
            state = .file(fileURL)
        } catch {
            state = .failed(error.localizedDescription)
        }
    }

    private func resolvedAttachment(accessToken: String) async throws -> TicketAttachment {
        if attachment.downloadUrl != nil || attachment.previewUrl != nil {
            return attachment
        }

        return try await session.client.attachmentMetadata(
            accessToken: accessToken,
            attachmentId: attachment.id
        ).data.attachment
    }

    private func isImage(_ attachment: TicketAttachment) -> Bool {
        if let mimeType = attachment.mimeType?.lowercased(), mimeType.hasPrefix("image/") {
            return true
        }

        return attachment.canPreview == true
    }

    private func writeTemporaryFile(data: Data, attachment: TicketAttachment) throws -> URL {
        let filename = sanitizedFilename(attachment.filename) ?? "attachment-\(attachment.id)"
        let url = FileManager.default.temporaryDirectory
            .appendingPathComponent("foxdesk-\(attachment.id)-\(filename)")
        try data.write(to: url, options: .atomic)
        return url
    }

    private func sanitizedFilename(_ filename: String?) -> String? {
        guard let filename = filename?.trimmingCharacters(in: .whitespacesAndNewlines), !filename.isEmpty else {
            return nil
        }

        let forbidden = CharacterSet(charactersIn: "/:\\?%*|\"<>")
        return filename
            .components(separatedBy: forbidden)
            .joined(separator: "-")
    }
}

private enum PreviewState {
    case loading
    case image(UIImage)
    case file(URL)
    case failed(String)
}

private struct QuickLookPreview: UIViewControllerRepresentable {
    let url: URL

    func makeUIViewController(context: Context) -> QLPreviewController {
        let controller = QLPreviewController()
        controller.dataSource = context.coordinator
        return controller
    }

    func updateUIViewController(_ controller: QLPreviewController, context: Context) {
        context.coordinator.url = url
        controller.reloadData()
    }

    func makeCoordinator() -> Coordinator {
        Coordinator(url: url)
    }

    final class Coordinator: NSObject, QLPreviewControllerDataSource {
        var url: URL

        init(url: URL) {
            self.url = url
        }

        func numberOfPreviewItems(in controller: QLPreviewController) -> Int {
            1
        }

        func previewController(_ controller: QLPreviewController, previewItemAt index: Int) -> QLPreviewItem {
            url as NSURL
        }
    }
}
