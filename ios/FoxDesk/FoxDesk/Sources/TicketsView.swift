import SwiftUI
import FoxDeskKit

struct TicketsView: View {
    @Environment(AppSession.self) private var session
    private let ticketCache = TicketListCacheStore()

    @State private var selectedView = "all"
    @State private var searchText = ""
    @State private var submittedSearch = ""
    @State private var tickets: [TicketSummary] = []
    @State private var totalCount: Int?
    @State private var hasMoreTickets = false
    @State private var isLoading = false
    @State private var isLoadingMore = false
    @State private var errorMessage: String?
    @State private var loadMoreError: String?
    @State private var cacheMessage: String?

    private let pageSize = 25

    private let viewOptions: [(id: String, title: String)] = [
        ("all", "All"),
        ("mine", "Mine"),
        ("new", "New"),
        ("waiting", "Waiting"),
        ("done", "Done")
    ]

    var body: some View {
        List {
            Section {
                Picker("Ticket view", selection: $selectedView) {
                    ForEach(viewOptions, id: \.id) { option in
                        Text(option.title).tag(option.id)
                    }
                }
                .pickerStyle(.segmented)
            }

            if let cacheMessage {
                Section {
                    Label(cacheMessage, systemImage: "clock.arrow.circlepath")
                        .font(.subheadline)
                        .foregroundStyle(.secondary)
                }
            }

            if let errorMessage {
                Section {
                    ContentUnavailableView("Could not load tickets", systemImage: "exclamationmark.triangle", description: Text(errorMessage))
                }
            } else if isLoading && tickets.isEmpty {
                Section {
                    HStack {
                        ProgressView()
                        Text("Loading tickets")
                            .foregroundStyle(.secondary)
                    }
                }
            } else if tickets.isEmpty {
                Section {
                    ContentUnavailableView("No tickets here", systemImage: "tray", description: Text(emptyStateDescription))
                }
            } else {
                Section(totalCountLabel) {
                    ForEach(tickets) { ticket in
                        NavigationLink {
                            TicketDetailView(ticketID: ticket.id, ticketHash: ticket.hash)
                        } label: {
                            TicketRow(ticket: ticket)
                        }
                    }

                    if hasMoreTickets {
                        loadMoreRow
                    }
                }
            }
        }
        .navigationTitle("Tickets")
        .searchable(text: $searchText, placement: .navigationBarDrawer(displayMode: .always), prompt: "Search tickets")
        .onSubmit(of: .search) {
            submittedSearch = searchText
        }
        .task(id: searchText) {
            await updateSubmittedSearch()
        }
        .task(id: selectedView + submittedSearch) {
            await loadTickets(reset: true)
        }
        .refreshable {
            await loadTickets(reset: true)
        }
    }

    @ViewBuilder
    private var loadMoreRow: some View {
        if isLoadingMore {
            HStack {
                ProgressView()
                Text("Loading more tickets")
                    .foregroundStyle(.secondary)
            }
        } else {
            VStack(alignment: .leading, spacing: 6) {
                Button {
                    Task { await loadMoreTickets() }
                } label: {
                    Label("Load more tickets", systemImage: "arrow.down.circle")
                }

                if let loadMoreError {
                    Text(loadMoreError)
                        .font(.caption)
                        .foregroundStyle(.orange)
                }
            }
        }
    }

    private var totalCountLabel: String {
        if let totalCount {
            return "\(totalCount) tickets"
        }
        return "Tickets"
    }

    private var emptyStateDescription: String {
        let workspace = session.tenantState?.tenant.name.trimmingCharacters(in: .whitespacesAndNewlines)
        let email = session.user?.email.trimmingCharacters(in: .whitespacesAndNewlines)
        let account = [workspace, email]
            .compactMap { value -> String? in
                guard let value, !value.isEmpty else { return nil }
                return value
            }
            .joined(separator: " · ")

        if selectedView == "mine" {
            return account.isEmpty
                ? "Mine only shows tickets assigned to you. Switch to All to see workspace tickets."
                : "Mine only shows tickets assigned to you. \(account). Switch to All to see workspace tickets."
        }

        let view = currentViewTitle.lowercased()
        if account.isEmpty {
            return "No \(view) tickets were returned. Pull to refresh or try Search."
        }
        return "No \(view) tickets were returned for \(account). Pull to refresh or try Search."
    }

    private var currentViewTitle: String {
        viewOptions.first { $0.id == selectedView }?.title ?? "Selected"
    }

    private func updateSubmittedSearch() async {
        let trimmed = searchText.trimmingCharacters(in: .whitespacesAndNewlines)
        let current = submittedSearch.trimmingCharacters(in: .whitespacesAndNewlines)
        guard trimmed != current else { return }

        if trimmed.count < 2 {
            submittedSearch = ""
            return
        }

        do {
            try await Task.sleep(for: .milliseconds(250))
            guard !Task.isCancelled else { return }
            submittedSearch = trimmed
        } catch is CancellationError {
            return
        } catch {
            return
        }
    }

    private func loadTickets(reset: Bool) async {
        let userId = session.user?.id
        if reset, let userId {
            await loadCachedTickets(userId: userId)
        } else if reset {
            tickets = []
            totalCount = nil
            hasMoreTickets = false
            cacheMessage = nil
        }

        isLoading = true
        errorMessage = nil
        loadMoreError = nil
        defer { isLoading = false }

        do {
            let response = try await fetchTicketPage(offset: 0)
            tickets = response.data.tickets
            totalCount = response.data.pagination?.total
            hasMoreTickets = response.data.pagination?.hasMore ?? hasMoreFromTotal
            cacheMessage = nil
            if let userId {
                await saveCurrentTicketCache(userId: userId)
            }
        } catch {
            if tickets.isEmpty, let userId {
                await loadCachedTickets(userId: userId)
            }
            if tickets.isEmpty {
                errorMessage = error.localizedDescription
            } else {
                errorMessage = nil
                cacheMessage = "Showing saved tickets. Pull to refresh when you are back online."
            }
        }
    }

    private func loadMoreTickets() async {
        guard hasMoreTickets, !isLoadingMore else { return }
        isLoadingMore = true
        loadMoreError = nil
        defer { isLoadingMore = false }

        do {
            let response = try await fetchTicketPage(offset: tickets.count)
            appendUniqueTickets(response.data.tickets)
            totalCount = response.data.pagination?.total ?? totalCount
            hasMoreTickets = response.data.pagination?.hasMore ?? hasMoreFromTotal
            if let userId = session.user?.id {
                await saveCurrentTicketCache(userId: userId)
            }
        } catch {
            loadMoreError = error.localizedDescription
        }
    }

    private func fetchTicketPage(offset: Int) async throws -> AppEnvelope<TicketListPayload> {
        try await session.authenticated { accessToken in
            try await session.client.ticketList(
                accessToken: accessToken,
                view: apiView,
                search: submittedSearch,
                assignedTo: selectedView == "mine" ? "me" : nil,
                limit: pageSize,
                offset: offset
            )
        }
    }

    private var hasMoreFromTotal: Bool {
        guard let totalCount else {
            return false
        }
        return tickets.count < totalCount
    }

    private func appendUniqueTickets(_ newTickets: [TicketSummary]) {
        var seenIds = Set(tickets.map(\.id))
        let uniqueTickets = newTickets.filter { ticket in
            if seenIds.contains(ticket.id) {
                return false
            }
            seenIds.insert(ticket.id)
            return true
        }
        tickets.append(contentsOf: uniqueTickets)
    }

    private func saveCurrentTicketCache(userId: Int) async {
        try? await ticketCache.save(
            userId: userId,
            tenantId: cacheTenantId,
            listKey: ticketListCacheKey,
            tickets: tickets,
            totalCount: totalCount
        )
    }

    private var apiView: String {
        selectedView == "mine" ? "open" : selectedView
    }

    private var ticketListCacheKey: String {
        let normalizedSearch = submittedSearch
            .trimmingCharacters(in: .whitespacesAndNewlines)
            .lowercased()
        let assignment = selectedView == "mine" ? "me" : "all"
        return "view-\(apiView)-assigned-\(assignment)-search-\(normalizedSearch)"
    }

    private func loadCachedTickets(userId: Int) async {
        do {
            if let cached = try await ticketCache.load(userId: userId, tenantId: cacheTenantId, listKey: ticketListCacheKey) {
                tickets = cached.tickets
                totalCount = cached.totalCount
                hasMoreTickets = hasMoreFromTotal
                cacheMessage = "Showing saved tickets while FoxDesk refreshes."
            } else {
                tickets = []
                totalCount = nil
                hasMoreTickets = false
                cacheMessage = nil
            }
        } catch {
            tickets = []
            totalCount = nil
            hasMoreTickets = false
            cacheMessage = nil
        }
    }

    private var cacheTenantId: Int {
        session.tenantState?.tenant.id ?? session.user?.tenantId ?? 0
    }
}

private struct TicketRoute: Identifiable, Hashable {
    let id: Int
    let hash: String?
}
