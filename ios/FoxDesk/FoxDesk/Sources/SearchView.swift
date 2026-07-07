import SwiftUI
import FoxDeskKit

struct SearchView: View {
    @Environment(AppSession.self) private var session

    @State private var query = ""
    @State private var results: GlobalSearchResponse?
    @State private var completedQuery = ""
    @State private var isLoading = false
    @State private var errorMessage: String?

    private var sections: [(key: String, section: GlobalSearchSection)] {
        let preferredOrder = ["open_tickets", "done_tickets", "archived_tickets", "clients", "contacts"]
        let allSections = results?.sections ?? [:]
        return preferredOrder.compactMap { key in
            guard let section = allSections[key], !section.items.isEmpty else { return nil }
            return (key, section)
        }
    }

    var body: some View {
        List {
            if query.trimmingCharacters(in: .whitespacesAndNewlines).count < 2 {
                Section {
                    ContentUnavailableView("Search FoxDesk", systemImage: "magnifyingglass", description: Text("Find open tickets, done tickets, clients and contacts."))
                }
            } else if isLoading && results == nil {
                Section {
                    HStack {
                        ProgressView()
                        Text("Searching")
                            .foregroundStyle(.secondary)
                    }
                }
            } else if let errorMessage {
                Section {
                    ContentUnavailableView("Search failed", systemImage: "exclamationmark.triangle", description: Text(errorMessage))
                }
            } else if sections.isEmpty {
                Section {
                    ContentUnavailableView("No results", systemImage: "magnifyingglass", description: Text(noResultsDescription))
                }
            } else {
                if !completedQuery.isEmpty {
                    Section {
                        Label("Results for “\(completedQuery)”", systemImage: "magnifyingglass")
                            .font(.subheadline)
                            .foregroundStyle(.secondary)
                    }
                }

                ForEach(sections, id: \.key) { entry in
                    Section(entry.section.definition?.label ?? entry.key) {
                        ForEach(entry.section.items, id: \.stableID) { item in
                            SearchResultRow(item: item)
                        }
                    }
                }
            }
        }
        .navigationTitle("Search")
        .searchable(text: $query, placement: .navigationBarDrawer(displayMode: .always), prompt: "Ticket, client, or contact")
        .task(id: query) {
            await search()
        }
    }

    private var noResultsDescription: String {
        if completedQuery.isEmpty {
            return "Try a ticket code, client name, or subject."
        }
        return "No tickets, clients, or contacts matched “\(completedQuery)”."
    }

    private func search() async {
        let trimmed = query.trimmingCharacters(in: .whitespacesAndNewlines)
        guard trimmed.count >= 2 else {
            results = nil
            completedQuery = ""
            errorMessage = nil
            return
        }

        if trimmed != completedQuery {
            results = nil
            completedQuery = ""
            errorMessage = nil
        }

        do {
            try await Task.sleep(for: .milliseconds(250))
            guard !Task.isCancelled else { return }

            isLoading = true
            defer { isLoading = false }

            results = try await session.authenticated { accessToken in
                try await session.client.globalSearch(accessToken: accessToken, query: trimmed)
            }
            completedQuery = trimmed
            errorMessage = nil
        } catch is CancellationError {
            return
        } catch {
            errorMessage = error.localizedDescription
        }
    }
}

private struct SearchResultRow: View {
    let item: GlobalSearchItem

    var body: some View {
        Group {
            if item.type == "ticket" {
                NavigationLink {
                    TicketDetailView(ticketID: item.id)
                } label: {
                    rowContent
                }
            } else if item.type == "client" {
                NavigationLink {
                    ClientContextView(organizationID: item.id, fallbackName: item.title)
                } label: {
                    rowContent
                }
            } else if item.type == "contact", let organizationID = item.organizationId {
                NavigationLink {
                    ClientContextView(organizationID: organizationID, fallbackName: item.client ?? item.title)
                } label: {
                    rowContent
                }
            } else {
                rowContent
            }
        }
    }

    private var rowContent: some View {
        HStack(alignment: .top, spacing: 12) {
            Image(systemName: iconName)
                .foregroundStyle(.blue)
                .frame(width: 24)

            VStack(alignment: .leading, spacing: 4) {
                HStack(spacing: 6) {
                    if let code = item.code, !code.isEmpty {
                        Text(code)
                            .font(.caption.weight(.semibold))
                            .foregroundStyle(.secondary)
                    }
                    Text(item.title)
                        .font(.headline)
                        .lineLimit(2)
                }

                Text(subtitle)
                    .font(.subheadline)
                    .foregroundStyle(.secondary)
                    .lineLimit(2)
            }
        }
        .padding(.vertical, 4)
    }

    private var subtitle: String {
        let parts = [
            item.status,
            item.client,
            item.assignee,
            item.subtitle,
            item.updatedAt
        ]
        .compactMap { value -> String? in
            guard let value, !value.isEmpty else { return nil }
            return value
        }
        return parts.joined(separator: " · ")
    }

    private var iconName: String {
        switch item.type {
        case "ticket":
            return "doc.text"
        case "client":
            return "building.2"
        case "contact":
            return "person"
        default:
            return "magnifyingglass"
        }
    }
}
