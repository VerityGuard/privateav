# PrivateAV Integration Examples

Server-side integration examples for PrivateAV age verification.

## Quick Start

Choose your language and follow the setup instructions below.

### Prerequisites

- A PrivateAV account with API keys from your [Dashboard](https://portal.privateav.com)
- Secret key (`sk_...`) for server-side API calls

## Examples

| Language | Directory | Framework |
|----------|-----------|-----------|
| TypeScript | `typescript/` | Express |
| Python | `python/` | Flask |
| PHP | `php/` | Native |

## TypeScript/Express

```bash
cd typescript
npm install
cp .env.example .env
# Edit .env with your secret key
npm start
```

Open http://localhost:3000 and click "Verify Age" to test the flow.

## Python/Flask

```bash
cd python
pip install -r requirements.txt
cp .env.example .env
# Edit .env with your secret key
python server.py
```

Open http://localhost:5000 and click "Verify Age" to test the flow.

## PHP

```bash
cd php
cp .env.example .env
# Edit .env with your secret key
php -S localhost:8080
```

Open http://localhost:8080 and click "Verify Age" to test the flow.

## How It Works

Each example demonstrates the complete server-side flow:

1. **Create Session** - Call `POST /api/v1/sessions/create` with your secret key
2. **Redirect User** - Send user to the `verifyUrl` returned in the response
3. **Validate Result** - When user returns, call `POST /api/v1/sessions/validate`

## API Reference

- **Base URL**: `https://api.privateav.com/api/v1`
- **Create Session**: `POST /sessions/create`
- **Validate Session**: `POST /sessions/validate`

See the [full API documentation](https://docs.privateav.com/api/sessions) for details.

## Support

- Documentation: https://docs.privateav.com
- Email: support@privateav.com
