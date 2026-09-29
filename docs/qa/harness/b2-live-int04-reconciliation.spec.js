/*
 * REFERENCE COPY — this is a MOBILE-side live E2E harness (QA-RELEASE B2).
 *
 * It exercises the real mobile sync services against a real Laravel server and
 * is NOT runnable from the backend repo. To reproduce:
 *   1. Copy this file into the POS Mobile worktree at:
 *        src/__tests__/b2-live-int04-reconciliation.spec.js
 *   2. Start the backend on an isolated QA database and seed it:
 *        (guard requires QA_ALLOWED_DATABASES=pos_qa_b2)
 *        php artisan db:seed --class=P37E2EResetSeeder --force
 *        php artisan db:seed --class=QaB2FixtureSeeder --force
 *   3. From the mobile worktree run:
 *        B2_E2E_BASE_URL=http://127.0.0.1:18010 \
 *        B2_E2E_EMAIL=member-a@example.com B2_E2E_PASSWORD=password \
 *        B2_E2E_DB=pos_qa_b2 \
 *        npx vitest run src/__tests__/b2-live-int04-reconciliation.spec.js
 *
 * B2-2a (lost response + member->cashier recovery) is expected to PASS.
 * B2-2b (cashier retail sale stock link) is an `it.fails` tripwire that
 * documents mobile defect F5 until it is fixed.
 */

/**
 * QA-RELEASE B2 — LIVE INT-04 reconciliation E2E.
 *
 * Drives the REAL mobile sync services (push service, reconciliation service,
 * durable outbox, identity registry, local operation journal, Pinia stores)
 * against a REAL running Laravel backend. No HTTP or service mocks are used:
 * the only "cut" is the client discarding an already-received server response,
 * which is exactly the lost-response situation the contract must survive.
 *
 * Run (mobile worktree):
 *   B2_E2E_BASE_URL=http://127.0.0.1:18010 \
 *   B2_E2E_EMAIL=member-a@example.com B2_E2E_PASSWORD=password \
 *   B2_E2E_DB=pos_qa_b2 \
 *   npx vitest run src/__tests__/b2-live-int04-reconciliation.spec.js
 *
 * Without B2_E2E_BASE_URL the suite skips.
 */

// @vitest-environment node

import { execFileSync } from 'node:child_process'
import { describe, it, expect } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

import { createMemoryAdapter } from '../services/database/memoryAdapter'
import { createLocalOperationService } from '../services/database/localOperationService'
import { createSyncQueueService } from '../services/sync/syncQueueService'
import { createSyncIdentityRegistry } from '../services/sync/syncIdentityRegistry'
import { createSyncPushService } from '../services/sync/syncPushService'
import { createSyncPullService } from '../services/sync/syncPullService'
import { createSyncChangeTracker } from '../services/sync/syncTracker'
import {
  createSyncReconciliationService,
  SYNC_RECONCILIATION_RESULTS,
} from '../services/sync/syncReconciliationService'
import { useBusinessStore } from '../stores/businessStore'
import { useProductStore } from '../stores/productStore'
import { useCashStore } from '../stores/cashStore'
import { useCustomerStore } from '../stores/customerStore'
import { useTransactionStore } from '../stores/transactionStore'
import { useShiftStore } from '../stores/shiftStore'

const BASE = process.env?.B2_E2E_BASE_URL || ''
const EMAIL = process.env?.B2_E2E_EMAIL || 'member-a@example.com'
const PASSWORD = process.env?.B2_E2E_PASSWORD || 'password'
const DB = process.env?.B2_E2E_DB || 'pos_qa_b2'
const MYSQL = 'C:\\Program Files\\MariaDB 12.1\\bin\\mysql.exe'
const RUN_E2E = BASE.length > 0

function suffix() {
  return globalThis.crypto?.randomUUID?.().slice(0, 8) ?? Math.random().toString(36).slice(2, 10)
}

function mysqlScalar(query) {
  return execFileSync(
    MYSQL,
    ['-h', '127.0.0.1', '-P', '3306', '-u', 'root', '-B', '-N', '-D', DB, '-e', query],
    { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] },
  ).trim()
}

async function api(method, path, { token, body, query } = {}) {
  const url = new URL(BASE + path)
  if (query) for (const [k, v] of Object.entries(query)) url.searchParams.set(k, String(v))
  const headers = { Accept: 'application/json' }
  if (body !== undefined) headers['Content-Type'] = 'application/json'
  if (token) headers.Authorization = `Bearer ${token}`
  const res = await fetch(url, { method, headers, body: body !== undefined ? JSON.stringify(body) : undefined })
  return { status: res.status, data: await res.json().catch(() => null) }
}

async function deviceRuntime() {
  const pinia = createPinia()
  setActivePinia(pinia)
  const adapter = createMemoryAdapter()
  await adapter.initialize()

  const queueService = createSyncQueueService({ adapter, scheduler: null })
  createSyncChangeTracker({ pinia, queueService })
  const registry = createSyncIdentityRegistry({ adapter, scheduler: null })

  const businessStore = useBusinessStore(pinia)
  const productStore = useProductStore(pinia)
  const cashStore = useCashStore(pinia)
  const customerStore = useCustomerStore(pinia)
  const transactionStore = useTransactionStore(pinia)
  const shiftStore = useShiftStore(pinia)

  businessStore.mode = 'cloud'
  productStore.products = []
  productStore.categories = []
  productStore.stockMovements = []
  cashStore.entries = []
  customerStore.customers = []
  transactionStore.items = []
  shiftStore.$patch({ isOpen: false, openingBalance: 0, openedAt: null, id: null })

  const localOperations = createLocalOperationService({ adapter, scheduler: null, pinia })

  return { pinia, adapter, queueService, registry, productStore, cashStore, customerStore, transactionStore, localOperations }
}

async function bind(runtime, { businessId, outletId, deviceIdentifier, registeredDeviceId }) {
  await runtime.adapter.saveSyncPushBinding({ businessId, boundAt: new Date().toISOString() })
  await runtime.adapter.saveSyncPullBinding({ businessId, outletId, deviceIdentifier, registeredDeviceId, boundAt: new Date().toISOString() })
  await runtime.adapter.saveSyncPullState({ version: 1, cursor: 0, serverSequence: 0 })
  await runtime.adapter.saveSyncBootstrapState({ version: 1, businessId, outletId, deviceIdentifier, registeredDeviceId, status: 'staged', stagedAt: new Date().toISOString(), counts: {} })
}

/** Real HTTP push whose response is then discarded by the client (lost response). */
function cutAfterCommitTransport(token) {
  return async ({ body }) => {
    const res = await api('POST', '/api/sync/push', { token, body })
    if (res.status >= 200 && res.status < 300) {
      return { ok: false, status: 0, data: null, error: { status: 0, code: 'NETWORK_ERROR', message: 'RESPONSE_CUT_AFTER_SERVER_COMMIT' } }
    }
    return { ok: false, status: res.status, data: res.data, error: { code: res.data?.code ?? `HTTP_${res.status}`, message: res.data?.message ?? '' } }
  }
}

function realPushTransport(token) {
  return async ({ body }) => {
    const res = await api('POST', '/api/sync/push', { token, body })
    if (res.status >= 200 && res.status < 300) return { ok: true, status: res.status, data: res.data, error: null }
    return { ok: false, status: res.status, data: res.data, error: { code: res.data?.code ?? `HTTP_${res.status}`, message: res.data?.message ?? '' } }
  }
}

function realPullTransport(token) {
  return async ({ businessId, deviceIdentifier, after, limit }) => {
    const res = await api('GET', '/api/sync/pull', { token, query: { business_id: businessId, device_identifier: deviceIdentifier, after, limit } })
    if (res.status >= 200 && res.status < 300) return { ok: true, status: res.status, data: res.data, error: null }
    return { ok: false, status: res.status, data: res.data, error: { code: res.data?.code ?? `HTTP_${res.status}`, message: res.data?.message ?? '' } }
  }
}

function realStatusTransport(token) {
  return async ({ requestId, businessId, deviceIdentifier }) => {
    const res = await api('GET', `/api/sync/requests/${encodeURIComponent(requestId)}/status`, {
      token,
      query: { business_id: businessId, device_identifier: deviceIdentifier },
    })
    if (res.status >= 200 && res.status < 300) return { ok: true, status: res.status, data: res.data, error: null }
    return { ok: false, status: res.status, data: res.data, error: { status: res.status, code: res.data?.code } }
  }
}

describe.runIf(RUN_E2E)('B2 LIVE INT-04 reconciliation (real services + real Laravel)', () => {
  it('recovers a lost response after a member->cashier role change without duplication', async () => {
    // ── 0. Real login + context + device ─────────────────────────────────────
    const login = await api('POST', '/api/auth/login', { body: { email: EMAIL, password: PASSWORD } })
    expect(login.status).toBe(200)
    const token = login.data.data.token

    const ctx = await api('GET', '/api/mobile/context', { token, query: { device_identifier: 'QA-OWNER-A-DEV-1' } })
    expect(ctx.status).toBe(200)
    const biz = ctx.data.data.businesses[0]
    const businessId = biz.id
    const outletId = biz.outlets[0].id
    const deviceIdentifier = 'QA-OWNER-A-DEV-1'
    const registeredDeviceId = String(biz.device_context.id)
    expect(biz.role).toBe('member')

    const context = { user: { id: 1 }, selectedBusiness: { id: businessId }, selectedOutlet: { id: outletId }, cloudAccess: true, deviceIdentifier, registeredDeviceId }

    const runtime = await deviceRuntime()
    await bind(runtime, { businessId, outletId, deviceIdentifier, registeredDeviceId })

    // ── 1. Sync a product first (normal, committed) so a sale can reference it ─
    const catName = `B2Cat-${suffix()}`
    runtime.productStore.createCategory(catName)
    const created = runtime.productStore.createProduct({ name: `B2Prod ${suffix()}`, category: catName, price: 15000, cost: 8000, stock: 10, unit: 'pcs', kind: 'product' })
    expect(created.success).toBe(true)
    const productId = created.product.id

    const pushOk = createSyncPushService({ adapter: runtime.adapter, queueService: runtime.queueService, registry: runtime.registry, scheduler: null, tokenFetcher: async () => token, transport: realPushTransport(token) })
    const productPush = await pushOk.pushNow({ context })
    expect(productPush.ok, JSON.stringify(productPush)).toBe(true)
    expect(await runtime.queueService.countPending()).toBe(0)

    // ── 2. Offline cash sale, then a push whose response is lost ─────────────
    const sale = await runtime.localOperations.commitRetailSale({
      checkout: {
        items: [{ id: productId, name: created.product.name, price: 15000, qty: 1, hppSnapshot: 8000 }],
        subtotal: 15000, tax: 0, total: 15000, customer: 'Walk-in Customer',
        paymentMethod: 'cash', cashReceived: 20000, changeAmount: 5000,
      },
    })
    expect(sale).toBeTruthy()

    const cutPush = createSyncPushService({ adapter: runtime.adapter, queueService: runtime.queueService, registry: runtime.registry, scheduler: null, tokenFetcher: async () => token, transport: cutAfterCommitTransport(token) })
    const lost = await cutPush.pushNow({ context })
    expect(lost.acceptance, JSON.stringify(lost)).toBe('unknown')

    const envelope = await runtime.adapter.loadSyncPushInflight()
    expect(envelope, 'envelope must be retained after a lost response').not.toBeNull()
    const requestId = envelope.requestId
    const pendingAfterCut = await runtime.queueService.countPending()
    expect(pendingAfterCut).toBeGreaterThan(0)

    // Server actually committed (lost response ≠ not accepted).
    const committedCheck = await api('GET', `/api/sync/requests/${requestId}/status`, { token, query: { business_id: businessId, device_identifier: deviceIdentifier } })
    expect(committedCheck.data?.data?.status).toBe('committed')

    const salesBefore = Number(mysqlScalar(`SELECT COUNT(*) FROM sales WHERE business_id=${businessId}`))
    const cashBefore = Number(mysqlScalar(`SELECT COUNT(*) FROM cash_ledger WHERE business_id=${businessId}`))
    const movBefore = Number(mysqlScalar(`SELECT COUNT(*) FROM stock_movements WHERE business_id=${businessId}`))
    expect(salesBefore).toBe(1)
    expect(cashBefore).toBe(1)
    expect(movBefore).toBe(1)

    // ── 3. Role member -> cashier (server-side, reversible) ──────────────────
    const memberId = mysqlScalar("SELECT id FROM users WHERE email='" + EMAIL + "'")
    mysqlScalar(`UPDATE business_user SET role='cashier' WHERE user_id=${memberId} AND business_id=${businessId}`)
    const ctxCashier = await api('GET', '/api/mobile/context', { token, query: { device_identifier: deviceIdentifier } })
    expect(ctxCashier.data.data.businesses[0].role).toBe('cashier')

    // ── 4. Mobile checks the ORIGINAL request status -> committed -> CAS cleanup
    const recon = createSyncReconciliationService({ adapter: runtime.adapter, queueService: runtime.queueService, registry: runtime.registry, tokenFetcher: async () => token, statusTransport: realStatusTransport(token) })
    const result = await recon.reconcile({ context })

    expect(result.code, JSON.stringify(result)).toBe(SYNC_RECONCILIATION_RESULTS.COMMITTED)
    expect(result.requestId).toBe(requestId)
    expect(result.acceptance).toBe('accepted')
    expect(result.reconciliationRequired).toBe(false)
    expect(result.remaining).toBe(0)

    expect(await runtime.adapter.loadSyncPushInflight()).toBeNull()
    expect(await runtime.queueService.countPending()).toBe(0)

    // ── 5. No duplication from the role-changed recovery ─────────────────────
    expect(Number(mysqlScalar(`SELECT COUNT(*) FROM sync_requests WHERE request_id='${requestId}'`))).toBe(1)
    expect(Number(mysqlScalar(`SELECT COUNT(*) FROM sales WHERE business_id=${businessId}`))).toBe(salesBefore)
    expect(Number(mysqlScalar(`SELECT COUNT(*) FROM cash_ledger WHERE business_id=${businessId}`))).toBe(cashBefore)
    expect(Number(mysqlScalar(`SELECT COUNT(*) FROM stock_movements WHERE business_id=${businessId}`))).toBe(movBefore)

    // ── 6. not_found while in-flight, then committed ─────────────────────────
    // The push never reaches the server (dropped before send): the client keeps
    // the envelope and the server legitimately reports not_found.
    runtime.customerStore.createCustomer({ name: `B2Cust ${suffix()}`, phone: '0811', email: null })
    const offlinePush = createSyncPushService({ adapter: runtime.adapter, queueService: runtime.queueService, registry: runtime.registry, scheduler: null, tokenFetcher: async () => token, transport: async () => ({ ok: false, status: 0, data: null, error: { status: 0, code: 'NETWORK_ERROR', message: 'offline' } }) })
    const dropped = await offlinePush.pushNow({ context })
    expect(dropped.acceptance).toBe('unknown')
    const envelope2 = await runtime.adapter.loadSyncPushInflight()
    const requestId2 = envelope2.requestId
    const pending2 = await runtime.queueService.countPending()
    expect(pending2).toBeGreaterThan(0)

    const notFound = await recon.reconcile({ context })
    expect(notFound.code, JSON.stringify(notFound)).toBe(SYNC_RECONCILIATION_RESULTS.NOT_FOUND)
    expect(notFound.resolved).toBe(false)
    expect(notFound.interventionRequired).toBe(true)
    // nothing deleted, same request id retained
    const kept = await runtime.adapter.loadSyncPushInflight()
    expect(kept).not.toBeNull()
    expect(kept.requestId).toBe(requestId2)
    expect(await runtime.queueService.countPending()).toBe(pending2)

    // The very same request now reaches the server and commits (same request_id).
    const commit2 = await api('POST', '/api/sync/push', { token, body: { business_id: businessId, device_identifier: deviceIdentifier, request_id: requestId2, changes: kept.changes } })
    expect(commit2.status, JSON.stringify(commit2.data)).toBe(200)

    const committed = await recon.reconcile({ context })
    expect(committed.code, JSON.stringify(committed)).toBe(SYNC_RECONCILIATION_RESULTS.COMMITTED)
    expect(committed.requestId).toBe(requestId2)
    expect(await runtime.adapter.loadSyncPushInflight()).toBeNull()
    expect(await runtime.queueService.countPending()).toBe(0)

    // Customer stored exactly once; the request recorded exactly once.
    const custRows = Number(mysqlScalar(`SELECT COUNT(*) FROM customers WHERE business_id=${businessId}`))
    expect(custRows).toBe(1)
    expect(Number(mysqlScalar(`SELECT COUNT(*) FROM sync_requests WHERE request_id='${requestId2}'`))).toBe(1)

    // ── cleanup: restore the member role ─────────────────────────────────────
    mysqlScalar(`UPDATE business_user SET role='member' WHERE user_id=${memberId} AND business_id=${businessId}`)
  }, 180000)

  // EXPECTED FAILURE (tripwire) — known mobile defect F5/B2-2b:
  // `localOperationService.applySaleStockIdempotently` records the sale stock
  // movement without `transactionId`, so contractMapper cannot attach
  // `sale_sync_id`; the cashier-safe server contract then rejects the movement
  // with 403 `missing_sale_relation`. Remove `.fails` once F5 is fixed.
  it.fails('cashier retail sale via the production commit path syncs a sale-linked stock movement', async () => {
    const login = await api('POST', '/api/auth/login', { body: { email: 'cashier-a@example.com', password: PASSWORD } })
    expect(login.status).toBe(200)
    const token = login.data.data.token

    const ctx = await api('GET', '/api/mobile/context', { token, query: { device_identifier: 'QA-CASHIER-A-DEV-1' } })
    const biz = ctx.data.data.businesses[0]
    const businessId = biz.id
    const outletId = biz.outlets[0].id
    const deviceIdentifier = 'QA-CASHIER-A-DEV-1'
    const registeredDeviceId = String(biz.device_context.id)
    expect(biz.sync_capabilities.push_mode).toBe('cashier_safe')

    const context = { user: { id: 1 }, selectedBusiness: { id: businessId }, selectedOutlet: { id: outletId }, cloudAccess: true, deviceIdentifier, registeredDeviceId }

    const runtime = await deviceRuntime()
    await bind(runtime, { businessId, outletId, deviceIdentifier, registeredDeviceId })

    const pullService = createSyncPullService({ adapter: runtime.adapter, queueService: runtime.queueService, registry: runtime.registry, pinia: runtime.pinia, scheduler: null, tokenFetcher: async () => token, transport: realPullTransport(token) })
    const pulled = await pullService.pullNow({ context })
    expect(pulled.ok, JSON.stringify(pulled)).toBe(true)

    const product = runtime.productStore.products.find((p) => (p.kind ?? 'product') === 'product')
    expect(product, 'a physical product must be available after pull').toBeTruthy()

    const sale = await runtime.localOperations.commitRetailSale({
      checkout: {
        items: [{ id: product.id, name: product.name, price: 15000, qty: 1, hppSnapshot: Number(product.cost) || 0 }],
        subtotal: 15000, tax: 0, total: 15000, customer: 'Walk-in Customer',
        paymentMethod: 'cash', cashReceived: 20000, changeAmount: 5000,
      },
    })
    expect(sale).toBeTruthy()

    const pushService = createSyncPushService({ adapter: runtime.adapter, queueService: runtime.queueService, registry: runtime.registry, scheduler: null, tokenFetcher: async () => token, transport: realPushTransport(token) })
    const result = await pushService.pushNow({ context })

    // eslint-disable-next-line no-console
    console.log('CASHIER_PUSH_RESULT ' + JSON.stringify({ ok: result.ok, code: result.code, error: result.error }))

    expect(result.ok, JSON.stringify({ code: result.code, error: result.error })).toBe(true)
    expect(await runtime.queueService.countPending()).toBe(0)

    const linkedMovements = Number(mysqlScalar(`SELECT COUNT(*) FROM stock_movements WHERE business_id=${businessId} AND sale_sync_id IS NOT NULL`))
    expect(linkedMovements, 'the cashier sale stock movement must carry its sale link').toBeGreaterThan(0)
  }, 180000)
})
