import Foundation

public struct StagedAttachmentUploadState<ID: Hashable & Sendable>: Sendable {
    private var uploadedIDs: Set<ID> = []

    public init() {}

    public var uploadedCount: Int {
        uploadedIDs.count
    }

    public mutating func markUploaded(_ id: ID) {
        uploadedIDs.insert(id)
    }

    public func hasUploaded(_ id: ID) -> Bool {
        uploadedIDs.contains(id)
    }

    public func remaining<Attachment: Sendable>(
        from attachments: [Attachment],
        id: @Sendable (Attachment) -> ID
    ) -> [Attachment] {
        attachments.filter { !uploadedIDs.contains(id($0)) }
    }
}
