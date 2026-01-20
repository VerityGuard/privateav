import 'dotenv/config';
import express from 'express';
import session from 'express-session';

const app = express();
const PORT = process.env.PORT || 3000;

// Middleware
app.use(express.json());
app.use(express.urlencoded({ extended: false }));
app.use(
  session({
    secret: process.env.SESSION_SECRET || 'dev-secret',
    resave: false,
    saveUninitialized: false,
  })
);

// PrivateAV API configuration
const API_URL = 'https://api.privateav.com/api/v1';
const SECRET_KEY = process.env.PRIVATEAV_SECRET_KEY;

if (!SECRET_KEY) {
  console.error('Error: PRIVATEAV_SECRET_KEY is required');
  process.exit(1);
}

// Home page with verify button
app.get('/', (req, res) => {
  const verified = (req.session as any).verified;
  res.send(`
    <!DOCTYPE html>
    <html>
    <head>
      <title>PrivateAV Example</title>
      <style>
        body { font-family: system-ui; max-width: 600px; margin: 50px auto; padding: 20px; }
        .btn { background: #0d9488; color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; text-decoration: none; display: inline-block; }
        .btn:hover { background: #0f766e; }
        .status { padding: 16px; border-radius: 6px; margin: 20px 0; }
        .verified { background: #d1fae5; color: #065f46; }
        .unverified { background: #fef3c7; color: #92400e; }
      </style>
    </head>
    <body>
      <h1>PrivateAV Integration Example</h1>
      ${verified
        ? '<div class="status verified">Age verified successfully!</div>'
        : '<div class="status unverified">Age verification required</div>'
      }
      <a href="/verify" class="btn">Verify Age</a>
    </body>
    </html>
  `);
});

// Start verification - creates session and redirects
app.get('/verify', async (req, res) => {
  try {
    const returnUrl = `${req.protocol}://${req.get('host')}/verified`;

    const response = await fetch(`${API_URL}/sessions/create`, {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${SECRET_KEY}`,
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        returnUrl,
        externalUserId: 'example-user-123',
      }),
    });

    if (!response.ok) {
      const error = await response.json();
      return res.status(response.status).send(`Error creating session: ${JSON.stringify(error)}`);
    }

    const session = await response.json();

    // Redirect to PrivateAV verification
    res.redirect(session.verifyUrl);
  } catch (error) {
    console.error('Error:', error);
    res.status(500).send('Error creating verification session');
  }
});

// Handle verification callback
app.get('/verified', async (req, res) => {
  const { sessionId, status } = req.query;

  if (status === 'cancelled') {
    return res.redirect('/?cancelled=true');
  }

  if (!sessionId) {
    return res.status(400).send('Missing sessionId');
  }

  try {
    // Validate the session server-side
    const response = await fetch(`${API_URL}/sessions/validate`, {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${SECRET_KEY}`,
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ sessionId }),
    });

    const result = await response.json();

    if (result.accessGranted) {
      // Store verified status in session
      (req.session as any).verified = true;
      res.redirect('/');
    } else {
      res.send(`
        <!DOCTYPE html>
        <html>
        <head><title>Verification Failed</title></head>
        <body>
          <h1>Verification Failed</h1>
          <p>Status: ${result.status}</p>
          <a href="/">Try again</a>
        </body>
        </html>
      `);
    }
  } catch (error) {
    console.error('Validation error:', error);
    res.status(500).send('Error validating session');
  }
});

app.listen(PORT, () => {
  console.log(`Server running at http://localhost:${PORT}`);
});
