import SwiftUI
import UIKit
import FoxDeskKit

struct AccountView: View {
    @Environment(AppSession.self) private var session
    @Environment(PushRegistrationService.self) private var pushRegistration
    @State private var didCopyAPNsToken = false

    var body: some View {
        List {
            WorkspaceStateSection()

            Section("Notifications") {
                LabeledContent("Status", value: pushStatusText)

                Button {
                    Task { await pushRegistration.enableNotifications(session: session) }
                } label: {
                    Label("Enable push notifications", systemImage: "bell.badge")
                }
                .disabled(isPushActionDisabled)

                Text("FoxDesk will use notifications for new assigned work and important ticket updates.")
                    .font(.footnote)
                    .foregroundStyle(.secondary)
            }

            #if DEBUG
            Section("Push diagnostics") {
                LabeledContent("API", value: session.client.environment.baseURL.absoluteString)
                LabeledContent("APNs token", value: apnsTokenPreview)

                if let apnsToken = pushRegistration.apnsToken {
                    Text(apnsToken)
                        .font(.footnote.monospaced())
                        .foregroundStyle(.secondary)
                        .textSelection(.enabled)
                        .lineLimit(4)

                    Button {
                        UIPasteboard.general.string = apnsToken
                        didCopyAPNsToken = true
                    } label: {
                        Label(didCopyAPNsToken ? "Copied APNs token" : "Copy APNs token", systemImage: "doc.on.doc")
                    }
                } else {
                    Text("Enable push notifications on a physical iPhone to capture the device token for APNs smoke testing.")
                        .font(.footnote)
                        .foregroundStyle(.secondary)
                }
            }
            #endif

            Section("Account") {
                LabeledContent("Name", value: session.user?.name ?? "")
                LabeledContent("Email", value: session.user?.email ?? "")
                LabeledContent("Role", value: session.user?.role ?? "")
            }

            Section("Help and legal") {
                AccountLinkRow(
                    title: "Contact support",
                    systemImage: "questionmark.circle",
                    url: FoxDeskAccountLinks.supportEmail
                )
                AccountLinkRow(
                    title: "Privacy Policy",
                    systemImage: "hand.raised",
                    url: FoxDeskAccountLinks.privacy
                )
                AccountLinkRow(
                    title: "Terms",
                    systemImage: "doc.text",
                    url: FoxDeskAccountLinks.terms
                )
            }

            Section("Data requests") {
                AccountLinkRow(
                    title: "Request account deletion",
                    systemImage: "person.crop.circle.badge.xmark",
                    url: FoxDeskAccountLinks.accountDeletion
                )
                Text("We will verify the request before deleting account or workspace data.")
                    .font(.footnote)
                    .foregroundStyle(.secondary)
            }

            Section {
                Button("Sign out", role: .destructive) {
                    Task {
                        await session.signOut()
                    }
                }
            }
        }
        .navigationTitle("Account")
        .task {
            if session.tenantState == nil && !session.isLoadingTenantState {
                await session.refreshTenantState()
            }
        }
        .refreshable {
            await session.refreshTenantState()
        }
    }

    private var apnsTokenPreview: String {
        guard let token = pushRegistration.apnsToken, !token.isEmpty else {
            return "Not captured"
        }
        if token.count <= 16 {
            return token
        }
        return "\(token.prefix(8))...\(token.suffix(8))"
    }

    private var pushStatusText: String {
        switch pushRegistration.state {
        case .idle:
            return pushRegistration.apnsToken == nil ? "Not enabled" : "Ready"
        case .requestingPermission:
            return "Requesting permission"
        case .waitingForToken:
            return "Waiting for device token"
        case .registering:
            return "Registering"
        case .registered:
            return "Enabled"
        case .denied:
            return "Permission denied"
        case .failed(let message):
            return message
        }
    }

    private var isPushActionDisabled: Bool {
        switch pushRegistration.state {
        case .requestingPermission, .waitingForToken, .registering:
            return true
        default:
            return false
        }
    }
}

private enum FoxDeskAccountLinks {
    static let supportEmail = URL(string: "mailto:support@foxdesk.net?subject=FoxDesk%20iOS%20support")!
    static let accountDeletion = URL(string: "mailto:support@foxdesk.net?subject=FoxDesk%20account%20deletion%20request")!
    static let privacy = URL(string: "https://foxdesk.net/index.php?page=legal&type=privacy")!
    static let terms = URL(string: "https://foxdesk.net/index.php?page=legal&type=terms")!
}

private struct AccountLinkRow: View {
    let title: String
    let systemImage: String
    let url: URL

    var body: some View {
        Link(destination: url) {
            Label(title, systemImage: systemImage)
        }
    }
}

private struct WorkspaceStateSection: View {
    @Environment(AppSession.self) private var session

    var body: some View {
        Section("Workspace") {
            if let tenantState = session.tenantState {
                LabeledContent("Name", value: tenantState.tenant.name)
                LabeledContent("Status", value: accessLabel(tenantState))
                if let trialEndsAt = tenantState.tenant.trialEndsAt, !trialEndsAt.isEmpty {
                    LabeledContent("Trial ends", value: trialEndsAt)
                }
                if let notice = workspaceNotice(tenantState), !notice.isEmpty {
                    Text(notice)
                        .font(.footnote)
                        .foregroundStyle(tenantState.access.allowed ? Color.secondary : Color.orange)
                }
            } else if session.isLoadingTenantState {
                HStack {
                    ProgressView()
                    Text("Checking workspace")
                        .foregroundStyle(.secondary)
                }
            } else {
                Text(session.tenantStateError ?? "Workspace status is not available.")
                    .foregroundStyle(.secondary)
            }

            Button {
                Task { await session.refreshTenantState() }
            } label: {
                Label("Refresh workspace status", systemImage: "arrow.clockwise")
            }
        }
    }

    private func accessLabel(_ state: TenantStatePayload) -> String {
        if state.access.allowed {
            return state.billingActions?.noticeTitle?.isEmpty == false
                ? state.billingActions?.noticeTitle ?? "Active"
                : "Active"
        }
        return state.billingActions?.noticeTitle?.isEmpty == false
            ? state.billingActions?.noticeTitle ?? "Paused"
            : "Paused"
    }

    private func workspaceNotice(_ state: TenantStatePayload) -> String? {
        if state.access.message?.isEmpty == false {
            return state.access.message
        }
        if state.billingActions?.noticeBody?.isEmpty == false {
            return state.billingActions?.noticeBody
        }
        return nil
    }
}
