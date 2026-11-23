# Third-party notices

ReplyPilot was written from scratch using [OpenReply](https://github.com/diwenne/openreply) as a functional reference (feature set, Meta webhook/API behaviour, setup pitfalls). The keyword-normalization approach in `src/lib/keywords.ts` is adapted from OpenReply's `lib/utils/keyword-matcher.ts`. OpenReply is distributed under the MIT License, reproduced below.

---

MIT License

Copyright (c) 2026 Anish Raj
Copyright (c) 2026 Diwen Huang

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

---

npm dependencies (Express, pg, BullMQ, ioredis, bcryptjs, zod, React, Vite and others) are used under their own licenses; see each package in `node_modules`.
