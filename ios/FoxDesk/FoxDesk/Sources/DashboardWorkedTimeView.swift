import SwiftUI
import FoxDeskKit

struct WorkedTimeSection: View {
    let time: HomeTimeActivity
    @Binding var selectedPeriod: String
    @Binding var selectedScope: String
    let onSelectionChange: () -> Void

    private let periodOptions = [
        ("today", "Today"),
        ("this_week", "This week"),
        ("last_30_days", "Last 30 days"),
        ("this_month", "This month"),
        ("last_month", "Last month")
    ]

    var body: some View {
        Section {
            VStack(alignment: .leading, spacing: 16) {
                HStack(alignment: .firstTextBaseline) {
                    Text("Worked time")
                        .font(.headline)
                    Spacer()
                    if let label = time.period?.label, !label.isEmpty {
                        Text(label)
                            .font(.caption)
                            .foregroundStyle(.secondary)
                    }
                }

                if time.scope?.canViewTeam == true {
                    Picker("Time view", selection: $selectedScope) {
                        Text("My time").tag("mine")
                        Text("Team time").tag("team")
                    }
                    .pickerStyle(.segmented)
                    .onChange(of: selectedScope) { _, _ in
                        onSelectionChange()
                    }
                }

                Picker("Period", selection: $selectedPeriod) {
                    ForEach(periodOptions, id: \.0) { key, label in
                        Text(label).tag(key)
                    }
                }
                .pickerStyle(.menu)
                .onChange(of: selectedPeriod) { _, _ in
                    onSelectionChange()
                }

                WorkedTimeTotalsRow(
                    totals: time.totals ?? [:],
                    selectedPeriodKey: selectedPeriod,
                    selectedPeriodLabel: time.period?.label ?? "Last 30 days"
                )

                if let chart = time.chart, let days = chart.days, !days.isEmpty {
                    WorkedTimeChart(chart: chart)
                }

                if selectedScope == "mine", let entries = time.entries, !entries.isEmpty {
                    VStack(alignment: .leading, spacing: 10) {
                        Text("Recent work")
                            .font(.subheadline.weight(.semibold))
                            .foregroundStyle(.secondary)
                        ForEach(entries.prefix(3)) { entry in
                            NavigationLink {
                                TicketDetailView(ticketID: entry.ticketId, ticketHash: entry.ticketHash)
                            } label: {
                                WorkedTimeEntryRow(entry: entry)
                            }
                        }
                    }
                }

                if selectedScope == "team", let team = time.team, !team.isEmpty {
                    TeamActivityList(team: team)
                }
            }
            .padding(.vertical, 6)
        }
    }
}

private struct WorkedTimeTotalsRow: View {
    let totals: [String: HomeTimeTotal]
    let selectedPeriodKey: String
    let selectedPeriodLabel: String

    private var items: [(String, String)] {
        switch selectedPeriodKey {
        case "today":
            return [("today", "Today"), ("week", "This week"), ("month", "This month")]
        case "this_week":
            return [("today", "Today"), ("week", "This week"), ("month", "This month")]
        case "this_month":
            return [("today", "Today"), ("week", "This week"), ("month", "This month")]
        default:
            return [("today", "Today"), ("week", "This week"), ("selected", selectedPeriodLabel)]
        }
    }

    var body: some View {
        HStack(spacing: 10) {
            ForEach(items, id: \.0) { key, label in
                VStack(alignment: .leading, spacing: 4) {
                    Text(label)
                        .font(.caption2.weight(.semibold))
                        .foregroundStyle(.secondary)
                    Text(totals[key]?.label ?? "0 min")
                        .font(.subheadline.weight(.semibold))
                        .lineLimit(1)
                        .minimumScaleFactor(0.8)
                }
                .frame(maxWidth: .infinity, alignment: .leading)
                .padding(10)
                .background(.thinMaterial, in: RoundedRectangle(cornerRadius: 12, style: .continuous))
            }
        }
    }
}

private struct TeamActivityList: View {
    @Environment(AppSession.self) private var session

    let team: [HomeTeamTimeMember]

    @State private var selectedMember: HomeTeamTimeMember?

    var body: some View {
        VStack(alignment: .leading, spacing: 10) {
            Text("Team activity")
                .font(.subheadline.weight(.semibold))
                .foregroundStyle(.secondary)

            ForEach(team.prefix(5)) { member in
                Button {
                    selectedMember = member
                } label: {
                    TeamActivityRow(
                        member: member,
                        avatarURL: session.client.resourceURL(from: member.avatar)
                    )
                }
                .buttonStyle(.plain)
            }
        }
        .sheet(item: $selectedMember) { member in
            NavigationStack {
                TeamMemberWorkSheet(member: member)
            }
        }
    }
}

private struct TeamActivityRow: View {
    let member: HomeTeamTimeMember
    let avatarURL: URL?

    private var latestEntry: HomeTimeEntry? {
        member.latestEntry ?? member.entries?.first
    }

    private var selectedTotalLabel: String {
        member.totals?["selected"]?.label
            ?? member.totals?["month"]?.label
            ?? "0 min"
    }

    private var displayName: String {
        if !member.name.isEmpty {
            return member.name
        }
        if let email = member.email, !email.isEmpty {
            return email
        }
        return "Team member"
    }

    private var initials: String {
        let source = displayName
        let letters = source
            .split(separator: " ")
            .prefix(2)
            .compactMap(\.first)
            .map(String.init)
            .joined()
            .uppercased()
        return letters.isEmpty ? "?" : letters
    }

    var body: some View {
        rowContent(latestEntry: latestEntry)
        .padding(10)
        .background(Color.secondary.opacity(0.07), in: RoundedRectangle(cornerRadius: 12, style: .continuous))
    }

    private func rowContent(latestEntry: HomeTimeEntry?) -> some View {
        HStack(spacing: 10) {
            TeamMemberAvatarView(avatarURL: avatarURL, initials: initials)

            VStack(alignment: .leading, spacing: 4) {
                HStack(spacing: 6) {
                    Text(displayName)
                        .font(.subheadline.weight(.semibold))
                        .foregroundStyle(.primary)
                        .lineLimit(1)
                    if member.isRunning == true {
                        Text("Running")
                            .font(.caption2.weight(.semibold))
                            .foregroundStyle(.green)
                            .padding(.horizontal, 6)
                            .padding(.vertical, 3)
                            .background(Color.green.opacity(0.12), in: Capsule())
                    }
                }

                Text(latestEntryTitle(latestEntry))
                    .font(.caption)
                    .foregroundStyle(.secondary)
                    .lineLimit(1)
            }

            Spacer(minLength: 8)

            Text(selectedTotalLabel)
                .font(.subheadline.weight(.semibold))
                .foregroundStyle(.secondary)
                .lineLimit(1)

            Image(systemName: "chevron.right")
                .font(.caption.weight(.semibold))
                .foregroundStyle(.tertiary)
        }
    }

    private func latestEntryTitle(_ entry: HomeTimeEntry?) -> String {
        guard let entry else {
            return "No recent work"
        }
        if !entry.ticketTitle.isEmpty {
            return entry.ticketTitle
        }
        if let code = entry.ticketCode, !code.isEmpty {
            return code
        }
        return "Ticket #\(entry.ticketId)"
    }
}

private struct TeamMemberWorkSheet: View {
    @Environment(\.dismiss) private var dismiss

    let member: HomeTeamTimeMember

    private var displayName: String {
        if !member.name.isEmpty {
            return member.name
        }
        if let email = member.email, !email.isEmpty {
            return email
        }
        return "Team member"
    }

    private var entries: [HomeTimeEntry] {
        let memberEntries = member.entries ?? []
        if !memberEntries.isEmpty {
            return memberEntries
        }
        if let latest = member.latestEntry {
            return [latest]
        }
        return []
    }

    private var selectedTotalLabel: String {
        member.totals?["selected"]?.label
            ?? member.totals?["month"]?.label
            ?? "0 min"
    }

    var body: some View {
        List {
            Section {
                LabeledContent("Selected period", value: selectedTotalLabel)
                if member.isRunning == true {
                    Label("Timer is running", systemImage: "play.circle.fill")
                        .foregroundStyle(.green)
                }
            }

            if entries.isEmpty {
                Section {
                    ContentUnavailableView(
                        "No recent work",
                        systemImage: "clock",
                        description: Text("Recent ticket work for this person will appear here.")
                    )
                }
            } else {
                Section("Recent ticket work") {
                    ForEach(entries) { entry in
                        NavigationLink {
                            TicketDetailView(ticketID: entry.ticketId, ticketHash: entry.ticketHash)
                        } label: {
                            WorkedTimeEntryRow(entry: entry)
                        }
                    }
                }
            }
        }
        .navigationTitle(displayName)
        .navigationBarTitleDisplayMode(.inline)
        .toolbar {
            ToolbarItem(placement: .topBarTrailing) {
                Button("Done") {
                    dismiss()
                }
            }
        }
    }
}

private struct TeamMemberAvatarView: View {
    let avatarURL: URL?
    let initials: String

    var body: some View {
        ZStack {
            Circle()
                .fill(Color.accentColor.opacity(0.16))

            if let avatarURL, avatarURL.scheme?.hasPrefix("http") == true {
                AsyncImage(url: avatarURL) { phase in
                    switch phase {
                    case .success(let image):
                        image
                            .resizable()
                            .scaledToFill()
                    default:
                        initialsView
                    }
                }
            } else {
                initialsView
            }
        }
        .frame(width: 34, height: 34)
        .clipShape(Circle())
        .accessibilityLabel("Team member avatar")
    }

    private var initialsView: some View {
        Text(initials)
            .font(.caption.weight(.semibold))
            .foregroundStyle(Color.accentColor)
    }
}

private struct WorkedTimeChart: View {
    let chart: HomeTimeChart

    private let palette: [Color] = [.accentColor, .green, .orange, .purple, .pink, .teal]

    private var days: [HomeTimeChartDay] {
        chart.days ?? []
    }

    private var visibleUsers: [HomeTimeChartUser] {
        var seen = Set<Int>()
        return days
            .flatMap { $0.users ?? [] }
            .filter { user in
                guard user.minutes > 0, !seen.contains(user.userId) else {
                    return false
                }
                seen.insert(user.userId)
                return true
            }
            .sorted { $0.name < $1.name }
    }

    private var maxMinutes: Int {
        max(chart.maxMinutes ?? 0, days.map(\.minutes).max() ?? 0, 1)
    }

    var body: some View {
        VStack(alignment: .leading, spacing: 10) {
            HStack {
                Text(chartPeriodLabel)
                    .font(.subheadline.weight(.semibold))
                    .foregroundStyle(.secondary)
                Spacer()
                Text(chart.totalLabel ?? "\(chart.totalMinutes ?? 0) min")
                    .font(.caption.weight(.semibold))
                    .foregroundStyle(.secondary)
            }

            HStack(alignment: .bottom, spacing: 4) {
                ForEach(days) { day in
                    VStack(spacing: 5) {
                        chartBar(for: day)
                            .frame(height: barHeight(for: day.minutes), alignment: .bottom)
                            .accessibilityLabel(chartAccessibilityLabel(for: day))

                        if shouldShowLabel(for: day) {
                            Text(shortLabel(day.label ?? day.key))
                                .font(.caption2)
                                .foregroundStyle(.secondary)
                                .lineLimit(1)
                                .minimumScaleFactor(0.7)
                        }
                    }
                    .frame(maxWidth: .infinity)
                }
            }
            .frame(height: 116, alignment: .bottom)
            .padding(.top, 2)

            if !visibleUsers.isEmpty {
                LazyVGrid(columns: [GridItem(.adaptive(minimum: 120), spacing: 8)], alignment: .leading, spacing: 6) {
                    ForEach(Array(visibleUsers.enumerated()), id: \.element.id) { index, user in
                        HStack(spacing: 6) {
                            Circle()
                                .fill(color(for: user, index: index))
                                .frame(width: 8, height: 8)
                            Text(user.name)
                                .font(.caption2)
                                .foregroundStyle(.secondary)
                                .lineLimit(1)
                        }
                    }
                }
            }
        }
        .padding(12)
        .background(Color.secondary.opacity(0.08), in: RoundedRectangle(cornerRadius: 14, style: .continuous))
    }

    private var chartPeriodLabel: String {
        guard let first = days.first?.fullLabel, let last = days.last?.fullLabel else {
            return "Worked time"
        }
        return first == last ? first : "\(first) – \(last)"
    }

    @ViewBuilder
    private func chartBar(for day: HomeTimeChartDay) -> some View {
        if day.minutes <= 0 {
            RoundedRectangle(cornerRadius: 5, style: .continuous)
                .fill(Color.secondary.opacity(0.16))
                .frame(height: 6)
        } else {
            VStack(spacing: 0) {
                ForEach(Array(daySegments(for: day).reversed().enumerated()), id: \.element.id) { index, segment in
                    Rectangle()
                        .fill(segment.color)
                        .frame(height: segmentHeight(segment.minutes, dayTotal: day.minutes, dayHeight: barHeight(for: day.minutes)))
                        .accessibilityHidden(index > 0)
                }
            }
            .clipShape(RoundedRectangle(cornerRadius: 5, style: .continuous))
        }
    }

    private func daySegments(for day: HomeTimeChartDay) -> [ChartSegment] {
        let users = (day.users ?? []).filter { $0.minutes > 0 }
        if users.isEmpty {
            return [
                ChartSegment(
                    id: -1,
                    name: "Worked time",
                    minutes: day.minutes,
                    label: day.minutesLabel ?? "\(day.minutes) min",
                    color: .accentColor
                )
            ]
        }

        return users.enumerated().map { index, user in
            ChartSegment(
                id: user.userId,
                name: user.name,
                minutes: user.minutes,
                label: user.minutesLabel ?? "\(user.minutes) min",
                color: color(for: user, index: index)
            )
        }
    }

    private func barHeight(for minutes: Int) -> CGFloat {
        guard minutes > 0 else {
            return 6
        }
        let ratio = Double(minutes) / Double(maxMinutes)
        return max(8, CGFloat(ratio) * 76)
    }

    private func segmentHeight(_ minutes: Int, dayTotal: Int, dayHeight: CGFloat) -> CGFloat {
        guard dayTotal > 0 else {
            return 0
        }
        let ratio = Double(minutes) / Double(dayTotal)
        return max(2, CGFloat(ratio) * dayHeight)
    }

    private func color(for user: HomeTimeChartUser, index: Int) -> Color {
        let stableIndex = visibleUsers.firstIndex { $0.userId == user.userId } ?? index
        return palette[stableIndex % palette.count]
            .opacity(user.minutes > 0 ? 1 : 0.35)
    }

    private func shouldShowLabel(for day: HomeTimeChartDay) -> Bool {
        guard let index = days.firstIndex(of: day) else {
            return false
        }
        return index == 0 || index == days.count - 1 || index % 7 == 0
    }

    private func shortLabel(_ label: String) -> String {
        label.replacingOccurrences(of: ".", with: "")
    }

    private func chartAccessibilityLabel(for day: HomeTimeChartDay) -> String {
        let date = day.fullLabel?.isEmpty == false ? day.fullLabel ?? day.key : day.key
        let users = daySegments(for: day)
            .filter { $0.id >= 0 }
            .map { "\($0.name) \($0.label)" }
            .joined(separator: ", ")
        if users.isEmpty {
            return "\(date), \(day.minutesLabel ?? "\(day.minutes) min")"
        }
        return "\(date), \(day.minutesLabel ?? "\(day.minutes) min"), \(users)"
    }
}

private struct ChartSegment: Identifiable {
    let id: Int
    let name: String
    let minutes: Int
    let label: String
    let color: Color
}

private struct WorkedTimeEntryRow: View {
    let entry: HomeTimeEntry

    var body: some View {
        HStack(spacing: 10) {
            Image(systemName: "clock")
                .foregroundStyle(.blue)

            VStack(alignment: .leading, spacing: 3) {
                Text(entry.ticketTitle.isEmpty ? (entry.ticketCode ?? "Ticket #\(entry.ticketId)") : entry.ticketTitle)
                    .font(.subheadline.weight(.semibold))
                    .foregroundStyle(.primary)
                    .lineLimit(2)

                HStack(spacing: 6) {
                    if let code = entry.ticketCode, !code.isEmpty {
                        Text(code)
                    }
                    if let client = entry.clientName, !client.isEmpty {
                        Text(client)
                    }
                }
                .font(.caption)
                .foregroundStyle(.secondary)
                .lineLimit(1)
            }

            Spacer(minLength: 8)

            Text(entry.minutesLabel ?? "\(entry.minutes) min")
                .font(.subheadline.weight(.semibold))
                .foregroundStyle(.secondary)
        }
    }
}
