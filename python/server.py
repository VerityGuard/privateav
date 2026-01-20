import os
from flask import Flask, redirect, request, session, url_for
from dotenv import load_dotenv
import requests

load_dotenv()

app = Flask(__name__)
app.secret_key = os.getenv("FLASK_SECRET_KEY", "dev-secret")

# PrivateAV API configuration
API_URL = "https://api.privateav.com/api/v1"
SECRET_KEY = os.getenv("PRIVATEAV_SECRET_KEY")

if not SECRET_KEY:
    raise ValueError("PRIVATEAV_SECRET_KEY is required")


@app.route("/")
def home():
    verified = session.get("verified", False)
    status_class = "verified" if verified else "unverified"
    status_text = "Age verified successfully!" if verified else "Age verification required"

    return f"""
    <!DOCTYPE html>
    <html>
    <head>
      <title>PrivateAV Example</title>
      <style>
        body {{ font-family: system-ui; max-width: 600px; margin: 50px auto; padding: 20px; }}
        .btn {{ background: #0d9488; color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; text-decoration: none; display: inline-block; }}
        .btn:hover {{ background: #0f766e; }}
        .status {{ padding: 16px; border-radius: 6px; margin: 20px 0; }}
        .verified {{ background: #d1fae5; color: #065f46; }}
        .unverified {{ background: #fef3c7; color: #92400e; }}
      </style>
    </head>
    <body>
      <h1>PrivateAV Integration Example</h1>
      <div class="status {status_class}">{status_text}</div>
      <a href="/verify" class="btn">Verify Age</a>
    </body>
    </html>
    """


@app.route("/verify")
def verify():
    return_url = url_for("verified", _external=True)

    response = requests.post(
        f"{API_URL}/sessions/create",
        headers={
            "Authorization": f"Bearer {SECRET_KEY}",
            "Content-Type": "application/json",
        },
        json={
            "returnUrl": return_url,
            "externalUserId": "example-user-123",
        },
    )

    if not response.ok:
        return f"Error creating session: {response.text}", response.status_code

    data = response.json()
    return redirect(data["verifyUrl"])


@app.route("/verified")
def verified():
    session_id = request.args.get("sessionId")
    status = request.args.get("status")

    if status == "cancelled":
        return redirect("/?cancelled=true")

    if not session_id:
        return "Missing sessionId", 400

    response = requests.post(
        f"{API_URL}/sessions/validate",
        headers={
            "Authorization": f"Bearer {SECRET_KEY}",
            "Content-Type": "application/json",
        },
        json={"sessionId": session_id},
    )

    result = response.json()

    if result.get("accessGranted"):
        session["verified"] = True
        return redirect("/")
    else:
        return f"""
        <!DOCTYPE html>
        <html>
        <head><title>Verification Failed</title></head>
        <body>
          <h1>Verification Failed</h1>
          <p>Status: {result.get('status')}</p>
          <a href="/">Try again</a>
        </body>
        </html>
        """


if __name__ == "__main__":
    port = int(os.getenv("PORT", 5000))
    app.run(host="0.0.0.0", port=port, debug=True)
