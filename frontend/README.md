# Aviate web app (Angular)

The browser front end for Aviate. See the [main README](../README.md) for setup.

```bash
npm install
npx ng serve                 # http://localhost:4200, proxies /api etc. to the Docker stack on :8080
npx ng test --watch=false    # unit tests (Vitest)
npx ng build                 # production build in dist/frontend/browser
```
