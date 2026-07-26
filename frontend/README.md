# SvelteKit-testklient

Denne klient er kun et teknisk dashboard til API-flowet. Den er ikke det endelige
galaksekort.

```bash
cd frontend
npm install
npm run check
npm run dev -- --host 127.0.0.1
```

I produktion bør API og frontend ligge på samme origin eller have en eksplicit,
stram CORS-konfiguration. Tokenet ligger i localStorage i testklienten; en rigtig
udgave bør bruge Symfony Security og sikre, HttpOnly sessionscookies.
