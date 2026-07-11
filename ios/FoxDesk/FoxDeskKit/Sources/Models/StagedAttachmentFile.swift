import Foundation

public struct StagedAttachmentFile: Identifiable, Sendable {
    public let id: UUID
    public let fileURL: URL
    public let filename: String
    public let mimeType: String
    public let byteCount: Int64

    public init(data: Data, filename: String, mimeType: String) throws {
        let id = UUID()
        let destination = try Self.destinationURL(id: id, filename: filename)
        try data.write(to: destination, options: [.atomic, .completeFileProtection])

        self.id = id
        self.fileURL = destination
        self.filename = Self.safeFilename(filename)
        self.mimeType = mimeType
        self.byteCount = Int64(data.count)
    }

    public init(copying sourceURL: URL, filename: String, mimeType: String) throws {
        let id = UUID()
        let destination = try Self.destinationURL(id: id, filename: filename)
        try FileManager.default.copyItem(at: sourceURL, to: destination)
        try FileManager.default.setAttributes(
            [.protectionKey: FileProtectionType.complete],
            ofItemAtPath: destination.path
        )
        let values = try destination.resourceValues(forKeys: [.fileSizeKey])

        self.id = id
        self.fileURL = destination
        self.filename = Self.safeFilename(filename)
        self.mimeType = mimeType
        self.byteCount = Int64(values.fileSize ?? 0)
    }

    public var sizeLabel: String {
        ByteCountFormatter.string(fromByteCount: byteCount, countStyle: .file)
    }

    public var iconName: String {
        mimeType.lowercased().hasPrefix("image/") ? "photo" : "paperclip"
    }

    public func remove() {
        try? FileManager.default.removeItem(at: fileURL)
    }

    private static func destinationURL(id: UUID, filename: String) throws -> URL {
        let directory = FileManager.default.temporaryDirectory
            .appendingPathComponent("FoxDesk-Staged-Uploads", isDirectory: true)
        try FileManager.default.createDirectory(
            at: directory,
            withIntermediateDirectories: true,
            attributes: [.protectionKey: FileProtectionType.complete]
        )
        removeStaleFiles(in: directory)
        return directory.appendingPathComponent("\(id.uuidString)-\(safeFilename(filename))")
    }

    private static func removeStaleFiles(in directory: URL) {
        let cutoff = Date().addingTimeInterval(-24 * 60 * 60)
        guard let files = try? FileManager.default.contentsOfDirectory(
            at: directory,
            includingPropertiesForKeys: [.contentModificationDateKey],
            options: [.skipsHiddenFiles]
        ) else {
            return
        }

        for file in files {
            let modified = try? file.resourceValues(forKeys: [.contentModificationDateKey]).contentModificationDate
            if let modified, modified < cutoff {
                try? FileManager.default.removeItem(at: file)
            }
        }
    }

    private static func safeFilename(_ filename: String) -> String {
        let trimmed = filename.trimmingCharacters(in: .whitespacesAndNewlines)
        let source = trimmed.isEmpty ? "attachment" : trimmed
        let forbidden = CharacterSet(charactersIn: "/:\\?%*|\"<>")
        return source.components(separatedBy: forbidden).joined(separator: "-")
    }
}
