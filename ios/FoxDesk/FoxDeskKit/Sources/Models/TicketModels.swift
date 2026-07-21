import Foundation

public struct TicketListPayload: Decodable, Sendable {
    public let tickets: [TicketSummary]
    public let view: String?
    public let views: JSONValue?
    public let counts: JSONValue?
    public let pagination: Pagination?
    public let filters: JSONValue?
}

public struct TicketDetailPayload: Codable, Sendable, Equatable {
    public let ticket: TicketSummary
    public let comments: [TicketComment]
    public let attachments: [TicketAttachment]
    public let timeEntries: [TicketTimeEntry]
    public let actions: TicketActionsPayload?

    public init(
        ticket: TicketSummary,
        comments: [TicketComment],
        attachments: [TicketAttachment],
        timeEntries: [TicketTimeEntry],
        actions: TicketActionsPayload?
    ) {
        self.ticket = ticket
        self.comments = comments
        self.attachments = attachments
        self.timeEntries = timeEntries
        self.actions = actions
    }
}

public struct TicketActionsResponse: Codable, Sendable, Equatable {
    public let ticket: TicketSummary
    public let actions: TicketActionsPayload
}

public struct Pagination: Decodable, Sendable, Equatable {
    public let limit: Int?
    public let offset: Int?
    public let total: Int?
    public let hasMore: Bool?
}

public struct TicketSummary: Codable, Sendable, Equatable, Identifiable, Hashable {
    public let id: Int
    public let hash: String?
    public let code: String?
    public let title: String
    public let descriptionHtml: String?
    public let descriptionText: String?
    public let descriptionPreview: String?
    public let status: TicketStatus?
    public let priority: TicketPriority?
    public let client: TicketClient?
    public let requester: TicketPerson?
    public let assignee: TicketPerson?
    public let source: String?
    public let tags: [String]?
    public let dueDate: String?
    public let createdAt: String?
    public let updatedAt: String?
    public let url: String?
    public let attachmentCount: Int?
    public let workedMinutes: Int?
    public let workedLabel: String?
    public let isArchived: Bool?
}

public struct TicketStatus: Codable, Sendable, Equatable, Hashable {
    public let id: Int?
    public let name: String?
    public let color: String?
    public let group: String?
    public let isClosed: Bool?
}

public struct TicketPriority: Codable, Sendable, Equatable, Hashable {
    public let id: Int?
    public let name: String?
    public let color: String?
}

public struct TicketActionsPayload: Codable, Sendable, Equatable {
    public let primary: [TicketPrimaryAction]?
    public let statuses: [TicketStatusOption]?
    public let priorities: [TicketPriorityOption]?
    public let assignees: [TicketAssigneeOption]?
    public let timer: TicketTimerState?
}

public struct CreateTicketOptionsPayload: Decodable, Sendable, Equatable {
    public let clients: [TicketClientOption]
    public let statuses: [TicketStatusOption]
    public let priorities: [TicketPriorityOption]
    public let assignees: [TicketAssigneeOption]
    public let defaults: CreateTicketDefaults?
}

public struct CreateTicketDefaults: Decodable, Sendable, Equatable {
    public let statusId: Int?
    public let priorityId: Int?
    public let assigneeId: Int?
}

public struct TicketPrimaryAction: Codable, Sendable, Equatable, Hashable, Identifiable {
    public var id: String { key ?? label ?? "action" }

    public let key: String?
    public let label: String?
    public let icon: String?
    public let variant: String?
    public let statusId: Int?
}

public struct TicketStatusOption: Codable, Sendable, Equatable, Hashable, Identifiable {
    public let id: Int
    public let name: String
    public let color: String?
    public let group: String?
    public let isClosed: Bool?
    public let isCanceled: Bool?
}

public struct TicketPriorityOption: Codable, Sendable, Equatable, Hashable, Identifiable {
    public let id: Int
    public let name: String
    public let color: String?
}

public struct TicketAssigneeOption: Codable, Sendable, Equatable, Hashable, Identifiable {
    public let id: Int
    public let name: String
    public let email: String?
    public let role: String?
}

public struct TicketClientOption: Codable, Sendable, Equatable, Hashable, Identifiable {
    public let id: Int
    public let name: String
    public let email: String?
    public let isActive: Bool?
}

public struct TicketClient: Codable, Sendable, Equatable, Hashable {
    public let id: Int?
    public let name: String?
}

public struct TicketPerson: Codable, Sendable, Equatable, Hashable {
    public let id: Int?
    public let name: String?
}

public struct TicketComment: Codable, Sendable, Equatable, Identifiable {
    public let id: Int
    public let userId: Int?
    public let authorName: String?
    public let authorEmail: String?
    public let contentHtml: String?
    public let contentText: String?
    public let isInternal: Bool?
    public let createdAt: String?
}

public struct TicketAttachment: Codable, Sendable, Equatable, Identifiable {
    public let id: Int
    public let ticketId: Int?
    public let commentId: Int?
    public let filename: String?
    public let mimeType: String?
    public let fileSize: Int?
    public let fileSizeLabel: String?
    public let storageDriver: String?
    public let downloadUrl: String?
    public let previewUrl: String?
    public let canPreview: Bool?
    public let createdAt: String?
}

public struct AttachmentMetadataPayload: Decodable, Sendable, Equatable {
    public let attachment: TicketAttachment
}

public struct UploadResponse: Decodable, Sendable, Equatable {
    public let success: Bool?
    public let file: UploadedFile
}

public struct UploadedFile: Decodable, Sendable, Equatable {
    public let filename: String?
    public let originalName: String?
    public let mimeType: String?
    public let fileSize: Int?
    public let url: String?
    public let attachmentId: Int?
}

public struct TicketTimeEntry: Codable, Sendable, Equatable, Identifiable {
    public let id: Int
    public let commentId: Int?
    public let userId: Int?
    public let userName: String?
    public let startedAt: String?
    public let endedAt: String?
    public let durationMinutes: Int
    public let summary: String?
    public let isBillable: Bool?
}

public struct DeleteTicketItemPayload: Decodable, Sendable, Equatable {
    public let ticketId: Int
    public let commentId: Int?
    public let timeEntryId: Int?
    public let attachmentId: Int?
    public let deleted: Bool
    public let undoToken: String
    public let undoAction: String?
    public let undoSeconds: Int?
}

public struct RestoreTicketItemPayload: Decodable, Sendable, Equatable {
    public let ticketId: Int
    public let commentId: Int?
    public let timeEntryId: Int?
    public let attachmentId: Int?
    public let restored: Bool
}

public struct UpdateCommentPayload: Decodable, Sendable, Equatable {
    public let ticketId: Int
    public let commentId: Int
    public let content: String
    public let contentHtml: String?
    public let updated: Bool
}

public struct UpdateTimeEntryPayload: Decodable, Sendable, Equatable {
    public let ticketId: Int
    public let timeEntryId: Int
    public let startedAt: String
    public let endedAt: String
    public let durationMinutes: Int
    public let summary: String?
    public let isBillable: Bool
}

struct UpdateCommentRequest: Encodable {
    let commentId: Int
    let content: String
}

struct UpdateTimeEntryRequest: Encodable {
    let timeEntryId: Int
    let durationMinutes: Int
    let summary: String?
    let isBillable: Bool
    let startedAt: String?
    let endedAt: String?
}

struct DeleteCommentRequest: Encodable {
    let commentId: Int
}

struct DeleteTimeEntryRequest: Encodable {
    let timeEntryId: Int
}

struct DeleteAttachmentRequest: Encodable {
    let attachmentId: Int
}

struct RestoreTicketItemRequest: Encodable {
    let undoToken: String
}

public struct TicketTimerPayload: Decodable, Sendable, Equatable {
    public let ticket: TicketSummary
    public let timer: TicketTimerState
}

public struct TimerActionPayload: Decodable, Sendable, Equatable {
    public let ticket: TicketSummary
    public let timer: TicketTimerState
    public let action: String
    public let result: JSONValue?
}

public struct TicketTimerState: Codable, Sendable, Equatable {
    public let state: String
    public let entryId: Int?
    public let elapsedMinutes: Int
    public let elapsedLabel: String?
}

public struct TimerActionRequest: Encodable, Sendable {
    public let ticketId: Int
    public let action: String

    public init(ticketId: Int, action: String) {
        self.ticketId = ticketId
        self.action = action
    }
}

public struct UpdateTicketRequest: Encodable, Sendable {
    public let ticketId: Int
    public let statusId: Int?
    public let priorityId: Int?
    public let includePriorityId: Bool
    public let assigneeId: Int?
    public let includeAssigneeId: Bool
    public let isArchived: Bool?
    public let includeIsArchived: Bool

    public init(
        ticketId: Int,
        statusId: Int? = nil,
        priorityId: Int? = nil,
        includePriorityId: Bool = false,
        assigneeId: Int? = nil,
        includeAssigneeId: Bool = false,
        isArchived: Bool? = nil,
        includeIsArchived: Bool = false
    ) {
        self.ticketId = ticketId
        self.statusId = statusId
        self.priorityId = priorityId
        self.includePriorityId = includePriorityId
        self.assigneeId = assigneeId
        self.includeAssigneeId = includeAssigneeId
        self.isArchived = isArchived
        self.includeIsArchived = includeIsArchived
    }

    private enum CodingKeys: String, CodingKey {
        case ticketId
        case statusId
        case priorityId
        case assigneeId
        case isArchived
    }

    public func encode(to encoder: Encoder) throws {
        var container = encoder.container(keyedBy: CodingKeys.self)
        try container.encode(ticketId, forKey: .ticketId)
        try container.encodeIfPresent(statusId, forKey: .statusId)
        if includePriorityId {
            if let priorityId {
                try container.encode(priorityId, forKey: .priorityId)
            } else {
                try container.encodeNil(forKey: .priorityId)
            }
        }
        if includeAssigneeId {
            if let assigneeId {
                try container.encode(assigneeId, forKey: .assigneeId)
            } else {
                try container.encodeNil(forKey: .assigneeId)
            }
        }
        if includeIsArchived {
            try container.encode(isArchived ?? false, forKey: .isArchived)
        }
    }
}

public struct UpdateTicketResponse: Decodable, Sendable, Equatable {
    public let ticket: TicketSummary
    public let actions: TicketActionsPayload?
    public let updatedFields: [String]
}

public struct AddCommentRequest: Encodable, Sendable {
    public let ticketId: Int
    public let content: String
    public let isInternal: Bool
    public let skipNotification: Bool
    public let durationMinutes: Int?
    public let isBillable: Bool?
    public let timeSummary: String?
    public let manualDate: String?
    public let manualStartTime: String?
    public let manualEndTime: String?
    public let startedAt: String?
    public let endedAt: String?
    public let createdAt: String?

    public init(
        ticketId: Int,
        content: String,
        isInternal: Bool,
        skipNotification: Bool = false,
        durationMinutes: Int? = nil,
        isBillable: Bool? = nil,
        timeSummary: String? = nil,
        manualDate: String? = nil,
        manualStartTime: String? = nil,
        manualEndTime: String? = nil,
        startedAt: String? = nil,
        endedAt: String? = nil,
        createdAt: String? = nil
    ) {
        self.ticketId = ticketId
        self.content = content
        self.isInternal = isInternal
        self.skipNotification = skipNotification
        self.durationMinutes = durationMinutes
        self.isBillable = isBillable
        self.timeSummary = timeSummary
        self.manualDate = manualDate
        self.manualStartTime = manualStartTime
        self.manualEndTime = manualEndTime
        self.startedAt = startedAt
        self.endedAt = endedAt
        self.createdAt = createdAt
    }
}

public struct AddCommentResponse: Decodable, Sendable, Equatable {
    public let ticketId: Int?
    public let commentId: Int
    public let timeEntryId: Int?
    public let durationMinutes: Int?
    public let startedAt: String?
    public let endedAt: String?
}

public struct CreateTicketRequest: Encodable, Sendable {
    public let title: String
    public let description: String
    public let organizationId: Int?
    public let assigneeId: Int?
    public let priorityId: Int?
    public let statusId: Int?
    public let dueDate: String?
    public let tags: String?
    public let createdAt: String?

    public init(
        title: String,
        description: String,
        organizationId: Int? = nil,
        assigneeId: Int? = nil,
        priorityId: Int? = nil,
        statusId: Int? = nil,
        dueDate: String? = nil,
        tags: String? = nil,
        createdAt: String? = nil
    ) {
        self.title = title
        self.description = description
        self.organizationId = organizationId
        self.assigneeId = assigneeId
        self.priorityId = priorityId
        self.statusId = statusId
        self.dueDate = dueDate
        self.tags = tags
        self.createdAt = createdAt
    }
}

public struct CreateTicketResponse: Decodable, Sendable, Equatable {
    public let ticketId: Int
    public let ticketHash: String?
    public let ticketCode: String?
    public let ticket: TicketSummary?
}
