import SwiftUI
import FoxDeskKit

struct TimerControlSection: View {
    @Environment(AppSession.self) private var session

    let ticketID: Int
    let onChanged: () async -> Void

    @State private var timer: TicketTimerState?
    @State private var isWorking = false
    @State private var message: String?

    var body: some View {
        Section("Timer") {
            HStack {
                Label(timerLabel, systemImage: timerIcon)
                    .foregroundStyle(timerColor)
                Spacer()
                if isWorking {
                    ProgressView()
                }
            }

            if let message {
                Text(message)
                    .font(.footnote)
                    .foregroundStyle(.secondary)
            }

            HStack {
                ForEach(actions, id: \.id) { action in
                    actionButton(action)
                }
            }
        }
        .task {
            await loadTimer()
        }
    }

    private var timerLabel: String {
        guard let timer else { return "Timer not loaded" }
        switch timer.state {
        case "running":
            return "Running · \(timer.elapsedLabel ?? "\(timer.elapsedMinutes) min")"
        case "paused":
            return "Paused · \(timer.elapsedLabel ?? "\(timer.elapsedMinutes) min")"
        default:
            return "No active timer"
        }
    }

    private var timerIcon: String {
        switch timer?.state {
        case "running":
            return "pause.circle"
        case "paused":
            return "play.circle"
        default:
            return "timer"
        }
    }

    private var timerColor: Color {
        switch timer?.state {
        case "running":
            return .green
        case "paused":
            return .orange
        default:
            return .secondary
        }
    }

    private var actions: [(id: String, title: String, isPrimary: Bool)] {
        switch timer?.state {
        case "running":
            return [
                ("pause", "Pause", false),
                ("stop", "Stop", true),
                ("discard", "Discard", false)
            ]
        case "paused":
            return [
                ("resume", "Resume", true),
                ("stop", "Stop", false),
                ("discard", "Discard", false)
            ]
        default:
            return [("start", "Start timer", true)]
        }
    }

    @ViewBuilder
    private func actionButton(_ action: (id: String, title: String, isPrimary: Bool)) -> some View {
        if action.isPrimary {
            Button(action.title) {
                Task { await perform(action.id) }
            }
            .buttonStyle(.borderedProminent)
            .disabled(isWorking)
        } else {
            Button(action.title) {
                Task { await perform(action.id) }
            }
            .buttonStyle(.bordered)
            .disabled(isWorking)
        }
    }

    private func loadTimer() async {
        do {
            timer = try await session.authenticated { accessToken in
                try await session.client.ticketTimer(accessToken: accessToken, ticketId: ticketID)
            }.data.timer
            message = nil
        } catch {
            message = error.localizedDescription
        }
    }

    private func perform(_ action: String) async {
        isWorking = true
        message = nil
        defer { isWorking = false }

        do {
            let response = try await session.authenticated { accessToken in
                try await session.client.ticketTimerAction(
                    accessToken: accessToken,
                    ticketId: ticketID,
                    action: action
                )
            }
            timer = response.data.timer
            message = "Timer updated"
            await onChanged()
        } catch {
            message = error.localizedDescription
        }
    }
}
