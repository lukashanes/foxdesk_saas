import SwiftUI
import FoxDeskKit

struct LoginView: View {
    @Environment(AppSession.self) private var session

    let errorMessage: String?

    @State private var email = ""
    @State private var password = ""

    var body: some View {
        NavigationStack {
            VStack(alignment: .leading, spacing: 24) {
                Spacer(minLength: 24)

                VStack(alignment: .leading, spacing: 10) {
                    Image(systemName: "bubble.left.and.bubble.right.fill")
                        .font(.system(size: 42, weight: .semibold))
                        .foregroundStyle(.blue)

                    Text("FoxDesk")
                        .font(.largeTitle.bold())

                    Text("Sign in to manage tickets, replies, time, and attachments from your iPhone.")
                        .foregroundStyle(.secondary)
                        .fixedSize(horizontal: false, vertical: true)
                }

                VStack(spacing: 14) {
                    TextField("Email", text: $email)
                        .textInputAutocapitalization(.never)
                        .keyboardType(.emailAddress)
                        .textContentType(.username)
                        .autocorrectionDisabled()
                        .foxDeskTextField()

                    SecureField("Password", text: $password)
                        .textContentType(.password)
                        .foxDeskTextField()
                }

                if let errorMessage {
                    Text(errorMessage)
                        .font(.callout)
                        .foregroundStyle(.red)
                }

                Button {
                    Task {
                        await session.signIn(email: email, password: password)
                    }
                } label: {
                    Text("Sign in")
                        .frame(maxWidth: .infinity)
                }
                .buttonStyle(.borderedProminent)
                .controlSize(.large)
                .disabled(email.isEmpty || password.isEmpty)

                Link("Set or reset password", destination: URL(string: "https://app.foxdesk.net/index.php?page=forgot-password")!)
                    .font(.callout.weight(.medium))
                    .frame(maxWidth: .infinity, alignment: .center)

                Spacer()
            }
            .padding(24)
            .navigationTitle("Sign in")
        }
    }
}

struct TwoFactorView: View {
    @Environment(AppSession.self) private var session

    let challengeToken: String

    @State private var code = ""

    var body: some View {
        NavigationStack {
            VStack(alignment: .leading, spacing: 20) {
                Text("Two-factor code")
                    .font(.title.bold())

                Text("Enter the code from your authenticator app or a backup code.")
                    .foregroundStyle(.secondary)

                TextField("Code", text: $code)
                    .keyboardType(.numberPad)
                    .textContentType(.oneTimeCode)
                    .foxDeskTextField()

                Button {
                    Task {
                        await session.verifyTwoFactor(challengeToken: challengeToken, code: code)
                    }
                } label: {
                    Text("Continue")
                        .frame(maxWidth: .infinity)
                }
                .buttonStyle(.borderedProminent)
                .controlSize(.large)
                .disabled(code.isEmpty)

                Spacer()
            }
            .padding(24)
            .navigationTitle("Verify")
        }
    }
}

private extension View {
    func foxDeskTextField() -> some View {
        textFieldStyle(.roundedBorder)
            .font(.body)
    }
}
