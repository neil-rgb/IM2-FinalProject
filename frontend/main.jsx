import React, { useEffect, useRef, useState } from "react";
import { createRoot } from "react-dom/client";
import {
  BrowserRouter,
  Link,
  Route,
  Routes,
  useLocation,
  useNavigate,
  useSearchParams,
} from "react-router";
import {
  QueryClient,
  QueryClientProvider,
  useMutation,
  useQuery,
  useQueryClient,
} from "@tanstack/react-query";
import "./react.css";

const base = document.getElementById("btrack-app").dataset.base;
const url = (path) => base + path;
const storageCode = ({ cabinet, drawer, folder }) => {
  const part = (value, prefix, width = 1) => {
    const digits = String(value).match(/\d+/)?.[0];
    if (digits) return `${prefix}${String(Number(digits)).padStart(width, "0")}`;
    const suffix = String(value).replace(/^(cabinet|drawer|folder)\s*/i, "").replace(/[^a-z0-9]/gi, "").toUpperCase();
    return `${prefix}${suffix || "0"}`;
  };
  return `${part(cabinet, "C")}-${part(drawer, "D")}-${part(folder, "F", 2)}`;
};
const client = new QueryClient({
  defaultOptions: {
    queries: { staleTime: 15000, retry: 1 },
    mutations: { retry: false },
  },
});

async function request(path, options = {}) {
  const response = await fetch(url(path), {
    credentials: "same-origin",
    ...options,
  });
  if (response.status === 401 || response.status === 403) {
    if (response.status === 401)
      window.location.assign(url("login.php?notice=unauthorized"));
    throw new Error("Your session or account could not be verified.");
  }
  const result = await response.json();
  if (!response.ok)
    throw new Error(result.message || "The request could not be completed.");
  return result;
}

function Icon({ name }) {
  const paths = {
    menu: "M4 6h16M4 12h16M4 18h16",
    search: "M16 16l5 5M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0",
    filter: "M4 7h16M7 12h10m-7 5h4",
    record: "M5 3h10l5 5v13H5zM15 3v6h5M8 13h8M8 17h8",
    folder: "M3 7h7l2 2h9v12H3zM3 7V4h7l2 3",
    building:
      "M3 21h18M5 21V8l7-4 7 4v13M9 10h1M14 10h1M9 14h1M14 14h1M11 21v-4h2v4",
    reports: "M4 19V5m0 14h16M8 16v-4m4 4V8m4 8V5",
    trash: "M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13M10 10v7m4-7v7",
    exit: "M10 4H5v16h5M14 8l4 4-4 4M18 12H9",
    plus: "M12 4v16M4 12h16",
    edit: "M4 20l1-5L17 3l4 4L9 19zM14 6l4 4",
  };
  return (
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <path d={paths[name] || paths.record} />
    </svg>
  );
}

function ErrorMessage({ error, retry }) {
  if (!error) return null;
  return (
    <div className="modal-error-summary" role="alert">
      {error.message || error}
      {retry && (
        <button className="button button--secondary" onClick={retry}>
          Try again
        </button>
      )}
    </div>
  );
}

function useSave(data, notify) {
  const cache = useQueryClient();
  return useMutation({
    mutationFn: async ({ path, body }) => {
      body.set("_token", data.csrf);
      const result = await request(path, {
        method: "POST",
        body,
        headers: { "X-BTRACK-React": "1" },
      });
      if (result.redirect?.includes("login.php")) {
        window.location.assign(url("login.php?notice=unauthorized"));
        throw new Error("Sign in again to continue.");
      }
      if (!result.ok) {
        throw new Error(
          Object.values(result.errors || {}).join(" ") ||
            result.notice?.message ||
            "Review the form and try again.",
        );
      }
      return result;
    },
    onSuccess: async (result) => {
      await cache.invalidateQueries();
      notify(result.notice?.message || "Saved successfully.");
    },
  });
}

function Popup({ title, children, onClose, kind = "confirmation" }) {
  const dialog = useRef(null);
  useEffect(() => {
    const previous = document.activeElement;
    dialog.current.showModal();
    return () => previous?.focus();
  }, []);
  return (
    <dialog
      ref={dialog}
      className={`document-modal react-dialog popup-panel popup-panel--${kind}`}
      aria-label={title}
      onCancel={(event) => {
        event.preventDefault();
        onClose();
      }}
    >
      <header className="document-modal__header">
        <h2>{title}</h2>
        <button
          type="button"
          className="button button--secondary popup-close"
          onClick={onClose}
          aria-label="Close dialog"
        >
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" strokeWidth="1.75" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" /></svg>
        </button>
      </header>
      {children}
    </dialog>
  );
}

function DocumentForm({ data, document, notify, onDone, onCancel }) {
  const save = useSave(data, notify);
  const [category, setCategory] = useState(String(document?.type_id || ""));
  const [direction, setDirection] = useState(document?.direction || "Incoming");
  const [folder, setFolder] = useState(String(document?.location_id || ""));
  const editing = Boolean(document);
  const submit = async (event) => {
    event.preventDefault();
    const body = new FormData(event.currentTarget);
    if (editing) body.set("document_id", document.document_id);
    else body.set("_action", "create_document");
    try {
      await save.mutateAsync({
        path: editing ? "documents/update.php" : "dashboard.php?add=1",
        body,
      });
      onDone();
    } catch {
      /* The mutation error remains beside the form. */
    }
  };
  return (
    <form className={`react-form document-entry-form document-entry-form--compact${editing ? " document-entry-form--edit" : ""}`} onSubmit={submit}>
      <ErrorMessage error={save.error} />
      <fieldset disabled={save.isPending}>
        <div className="document-entry-sections">
        <section className="document-entry-main" aria-label="Document details">
        {editing && <h2>Document details</h2>}
        <div className="modal-form-grid">
          <label className="modal-field modal-field--wide">
            <span>Document title *</span>
            <input
              name="title"
              autoFocus={!editing}
              required
              maxLength={150}
              defaultValue={document?.title || ""}
            />
          </label>
          <label className="modal-field">
            <span>Category *</span>
            <select
              name={editing ? "category" : "type_id"}
              value={category}
              onChange={(event) => {
                setCategory(event.target.value);
                setFolder("");
              }}
              required
            >
              <option value="">Choose category</option>
              {data.types.map((type) => (
                <option key={type.type_id} value={type.type_id}>
                  {type.type_name}
                </option>
              ))}
            </select>
          </label>
          <label className="modal-field">
            <span>Direction *</span>
            <select
              name="direction"
              value={direction}
              onChange={(event) => setDirection(event.target.value)}
            >
              <option>Incoming</option>
              <option>Outgoing</option>
            </select>
          </label>
          <label className="modal-field">
            <span>
              {direction === "Incoming"
                ? "Received from / Sender"
                : "Sent to / Recipient"}{" "}
              *
            </span>
            <input
              name="sender_recipient"
              required
              maxLength={100}
              defaultValue={document?.sender_recipient || ""}
            />
          </label>
          <label className="modal-field">
            <span>
              Date {direction === "Incoming" ? "received" : "released"} *
            </span>
            <input
              type="date"
              name="date_received"
              required
              max={data.today}
              defaultValue={document?.date_received || data.today}
            />
          </label>
        </div>
        </section>
        <section className="document-entry-filing" aria-label="Paper filing">
        {editing && <h2>Paper filing</h2>}
        <div className="modal-form-grid">
          <label className="modal-field modal-field--wide document-entry-location">
            <span>Original paper folder *</span>
            <select
              name="location_id"
              disabled={!category}
              required
              value={folder}
              onChange={(event) => setFolder(event.target.value)}
            >
              <option value="">
                {category ? "Choose folder" : "Choose a category first"}
              </option>
              {data.locations
                .filter((location) => String(location.type_id) === category)
                .map((location) => (
                  <option
                    key={location.location_id}
                    value={location.location_id}
                  >
                    {storageCode(location)}
                  </option>
                ))}
            </select>
          </label>
          <label className="modal-field modal-field--wide document-entry-attachment">
            <span>Soft copy · optional</span>
            <input type="file" name="soft_copy" accept=".pdf,.jpg,.jpeg,.png" />
            <small className="document-entry-hint">
              PDF, JPG or PNG · up to 10 MB
              {document?.soft_copy_name
                ? ` · Current file: ${document.soft_copy_name}`
                : ""}
            </small>
          </label>
        </div>
        </section>
        </div>
        <div className="form-actions">
          <button
            className="button button--secondary"
            type="button"
            onClick={onCancel}
          >
            Cancel
          </button>
          <button className="button button--primary" type="submit">
            {save.isPending
              ? "Saving…"
              : editing
                ? "Save changes"
                : "Record document"}
          </button>
        </div>
      </fieldset>
    </form>
  );
}

function Records({ data, list, notify }) {
  const [params, setParams] = useSearchParams();
  const [adding, setAdding] = useState(params.has("add"));
  const suffix = params.toString() ? `?${params}` : "";
  const boardParams = new URLSearchParams(params);
  boardParams.delete("category");
  const boardSuffix = boardParams.toString() ? `?${boardParams}` : "";
  const categories = [...data.types].sort((a, b) => a.type_name.localeCompare(b.type_name));
  const docs = data.documents.filter(
    (document) =>
      (!params.get("search") ||
        `${document.tracking} ${document.title}`
          .toLowerCase()
          .includes(params.get("search").toLowerCase())) &&
      (!params.get("year") ||
        document.recorded.startsWith(params.get("year"))) &&
      (!list || !params.get("category") || document.category === params.get("category")) &&
      (!params.get("status") || document.status === params.get("status")),
  );
  const close = () => {
    setAdding(false);
    if (params.has("add")) {
      params.delete("add");
      setParams(params, { replace: true });
    }
  };
  return (
    <>
      <section className="page-heading">
        <div className="page-title-lockup">
          <span className="react-barangay">
            <Icon name="building" />
          </span>
          <div className="page-title-copy">
            <h1>{data.profile?.barangay_name || "BARANGAY SAN ROQUE"}</h1>
            <p>
              {data.profile?.location || "Santo Domingo, Albay, Philippines"}
            </p>
          </div>
        </div>
      </section>
      <div className="records-toolbar">
        <nav className="dashboard-tabs" aria-label="Record view">
          <Link
            to={`/dashboard.php${boardSuffix}`}
            className={!list ? "is-active" : ""}
          >
            Board
          </Link>
          <Link
            to={`/documents/index.php${suffix}`}
            className={list ? "is-active" : ""}
          >
            List
          </Link>
        </nav>
        {list && (
          <label className="list-category-control">
            <select
              className="button button--secondary list-category-select"
              aria-label="Choose document category"
              value={params.get("category") || ""}
              onChange={(event) => {
                const next = new URLSearchParams(params);
                next.delete("id");
                next.delete("from");
                event.target.value ? next.set("category", event.target.value) : next.delete("category");
                setParams(next, { replace: true });
              }}
            >
              <option value="">All categories</option>
              {categories.map((type) => (
                <option key={type.type_id} value={type.type_name}>
                  {type.type_name}
                </option>
              ))}
            </select>
          </label>
        )}
        <button
          className="button button--primary"
          onClick={() => setAdding(true)}
        >
          <Icon name="plus" />
          Record document
        </button>
        <span className="react-result-count" role="status">{docs.length} {docs.length === 1 ? "record" : "records"}</span>
      </div>
      {docs.length === 0 && (
        <div className="list-state">
          <h3>
            {data.documents.length
              ? "No matching records"
              : "No documents recorded yet"}
          </h3>
          <p>
            {data.documents.length
              ? "Adjust the search or clear your filters."
              : "Record your first document to get started."}
          </p>
          {data.documents.length > 0 && <button className="button button--secondary" type="button" onClick={() => setParams({})}>Clear search and filters</button>}
        </div>
      )}
      {docs.length > 0 && (list ? (
        <RecordTable documents={docs} showCategory={false} />
      ) : (
        <section className="cabinet-board" aria-label="Document cabinets" tabIndex={0}>
          {data.types.map((type) => (
            <article className="cabinet" key={type.type_id}>
              <header className="cabinet-header">
                <h3 title={type.type_name}>{type.type_name}</h3>
                <span className="cabinet-count" aria-label={`${docs.filter((d) => d.category === type.type_name).length} records`}>
                  {docs.filter((d) => d.category === type.type_name).length}
                </span>
                <p className="cabinet-number">
                  CABINET {String(type.type_id - 400).padStart(2, "0")}
                </p>
              </header>
              <div className="cabinet-body">
                {!docs.some(d => d.category === type.type_name) && <p className="cabinet-empty-message">No records in this category.</p>}
                {docs
                  .filter((d) => d.category === type.type_name)
                  .map((document) => (
                    <Link
                      className="document-folder"
                      reloadDocument
                      key={document.id}
                      to={`/documents/view.php?id=${document.id}&${params}`}
                    >
                      <span className="document-code">{document.tracking}</span>
                      <h4>{document.title}</h4>
                      <div className="document-recorded">
                        <span>Recorded</span>
                        <time dateTime={document.recorded_at.slice(0, 10)}>{document.recorded_at.slice(0, 10)}</time>
                      </div>
                    </Link>
                  ))}
              </div>
            </article>
          ))}
        </section>
      ))}
      {adding && (
        <Popup title="Record document" onClose={close} kind="form">
          <DocumentForm
            data={data}
            notify={notify}
            onDone={close}
            onCancel={close}
          />
        </Popup>
      )}
    </>
  );
}

function RecordTable({ documents, showCategory = true }) {
  const location = useLocation();
  const params = new URLSearchParams(location.search);
  params.delete("id");
  params.delete("profile");
  if (location.pathname.endsWith("/inventory.php")) {
    params.set("from", "inventory");
    params.set("inventory_location", params.get("location_id") || "");
    params.delete("location_id");
  } else {
    params.set("from", "list");
  }
  return (
    <div className="record-table-wrap document-list-wrap" role="region" aria-label="Document records" tabIndex={0}>
      <table className="record-table document-list-table">
        <caption className="sr-only">{showCategory ? "Document records with category, processing status, and date recorded" : "Document records with processing status and date recorded"}</caption>
        <colgroup><col className="list-code-column" /><col />{showCategory && <col className="list-category-column" />}<col className="list-status-column" /><col className="list-date-column" /></colgroup>
        <thead>
          <tr>
            <th scope="col">Document code</th>
            <th scope="col">Document title</th>
            {showCategory && <th scope="col">Category</th>}
            <th scope="col">Status</th>
            <th scope="col">Recorded</th>
          </tr>
        </thead>
        <tbody>
          {documents.map((document) => (
            <tr key={document.id}>
              <td>{document.tracking}</td>
              <td>
                <Link
                  className="record-title"
                  reloadDocument
                  to={`/documents/view.php?id=${document.id}&${params}`}
                >
                  {document.title}
                </Link>
              </td>
              {showCategory && <td>{document.category}</td>}
              <td><span className={`list-record-status list-record-status--${document.status.toLowerCase().replaceAll(" ", "-")}`}>{document.status}</span></td>
              <td><time dateTime={document.recorded_at.slice(0, 10)}>{document.recorded_at.slice(0, 10)}</time></td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function OriginalRecordPage() {
  const location = useLocation();
  useEffect(() => {
    window.location.replace(url(`documents/view.php${location.search}`));
  }, [location.search]);
  return <div className="page-transition-placeholder" aria-busy="true" aria-label="Opening record" />;
}

function Record({ data, notify, editing }) {
  const [params] = useSearchParams();
  const id = params.get("id");
  const navigate = useNavigate();
  const query = useQuery({
    queryKey: ["record", id],
    queryFn: () => request(`api.php?record=${encodeURIComponent(id || "")}`),
  });
  const save = useSave(data, notify);
  const [action, setAction] = useState(null);
  if (query.isPending) return <div className="page-transition-placeholder" aria-busy="true" aria-label="Opening record" />;
  if (query.error)
    return <ErrorMessage error={query.error} retry={query.refetch} />;
  const document = query.data.document;
  const next =
    document.status === "Pending"
      ? "In Process"
      : document.status === "In Process"
        ? "Completed"
        : null;
  const type = data.types.find(
    (type) => String(type.type_id) === String(document.type_id),
  );
  const folder = data.locations.find(
    (folder) => String(folder.location_id) === String(document.location_id),
  );
  const recordPath = `/documents/view.php?${params}`;
  const perform = async (event) => {
    event.preventDefault();
    const body = new FormData(event.currentTarget);
    body.set("document_id", document.document_id);
    body.set("action", "move");
    body.set("current_status", document.status);
    try {
      const result = await save.mutateAsync({
        path:
          action === "status"
            ? "documents/status.php"
            : "documents/recycle-bin.php",
        body,
      });
      setAction(null);
      if (action === "delete") {
        const destination = new URL(
          result.redirect,
          new URL(url("documents/recycle-bin.php"), window.location.origin),
        );
        navigate(
          destination.pathname.slice(base.length - 1) + destination.search,
        );
      }
    } catch {
      /* Keep the dialog and its entered remark open for correction. */
    }
  };
  if (editing && !document.deleted_at)
    return (
      <>
        <header className="record-detail-heading document-edit-heading">
          <h1>Edit document details</h1>
          <span>{document.tracking_number}</span>
        </header>
        <section className="record-detail-panel document-edit-panel">
          <DocumentForm
            key={id}
            data={data}
            document={document}
            notify={notify}
            onDone={() => navigate(recordPath)}
            onCancel={() => navigate(recordPath)}
          />
        </section>
      </>
    );
  return (
    <>
      <header className="record-detail-heading record-overview-heading">
        <div className="record-detail-title">
          <p>{document.tracking_number}</p>
          <h1>{document.title}</h1>
        </div>
        <div className="react-actions">
          <span className="record-status">
            {document.deleted_at ? "In Recycle Bin" : document.status}
          </span>
          {!document.deleted_at && (
            <>
              {next && (
                <button
                  className="button button--secondary"
                  onClick={() => {
                    save.reset();
                    setAction("status");
                  }}
                >
                  Change status
                </button>
              )}
              <Link
                className="button button--primary"
                to={`/documents/edit.php?${params}`}
              >
                <Icon name="edit" />
                Edit details
              </Link>
              <button
                className="button button--secondary"
                aria-label="Move to Recycle Bin"
                title="Move to Recycle Bin"
                onClick={() => {
                  save.reset();
                  setAction("delete");
                }}
              >
                <Icon name="trash" />
              </button>
            </>
          )}
        </div>
      </header>
      <div className="record-detail-columns record-overview-columns">
        <section className="record-detail-panel">
          <h2>Document details</h2>
          <dl className="react-details">
            {[
              ["Category", type?.type_name],
              ["Direction", document.direction],
              ["Sender / Recipient", document.sender_recipient],
              ["Date received / released", document.date_received],
            ].map(([label, value]) => (
              <div key={label}>
                <dt>{label}</dt>
                <dd>{value}</dd>
              </div>
            ))}
          </dl>
        </section>
        <section className="record-detail-panel">
          <h2>Filing and entry</h2>
          <dl className="react-details">
            <div>
              <dt>Original paper location</dt>
              <dd>
                {folder
                  ? storageCode(folder)
                  : "Legacy storage location"}
              </dd>
            </div>
            <div>
              <dt>Recorded by</dt>
              <dd>{document.recorded_by_name}</dd>
            </div>
            <div>
              <dt>Date recorded</dt>
              <dd>{document.created_at}</dd>
            </div>
          </dl>
          {document.soft_copy_name && (
            <a
              className="button button--secondary"
              href={url(`documents/soft-copy.php?id=${id}`)}
              target="_blank"
              rel="noreferrer"
            >
              Open {document.soft_copy_name}
            </a>
          )}
        </section>
      </div>
      <section className="record-detail-panel react-history">
        <h2>Processing history</h2>
        <div className="record-table-wrap">
          <table className="record-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Action</th>
                <th>Handled by</th>
                <th>Details</th>
              </tr>
            </thead>
            <tbody>
              {query.data.history.map((row) => (
                <tr key={row.history_id}>
                  <td>{row.action_date}</td>
                  <td>{row.action_type}</td>
                  <td>{row.handled_by_name}</td>
                  <td>
                    {row.remarks}
                    {row.new_status ? ` · ${row.new_status}` : ""}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>
      {action && (
        <Popup
          title={
            action === "status"
              ? `Change status to ${next}?`
              : "Move record to Recycle Bin?"
          }
          onClose={() => !save.isPending && setAction(null)}
        >
          <form className="react-form" onSubmit={perform}>
            <ErrorMessage error={save.error} />
            {action === "status" ? (
              <label className="modal-field">
                <span>Processing remark *</span>
                <textarea name="remarks" required maxLength={255} />
              </label>
            ) : (
              <p>
                {document.tracking_number} will leave active records. You can
                restore it from the Recycle Bin.
              </p>
            )}
            <div className="form-actions">
              <button
                type="button"
                disabled={save.isPending}
                className="button button--secondary"
                onClick={() => setAction(null)}
              >
                Cancel
              </button>
              <button
                className="button button--primary"
                disabled={save.isPending}
              >
                {save.isPending ? "Saving…" : "Confirm"}
              </button>
            </div>
          </form>
        </Popup>
      )}
    </>
  );
}

function RecycleBin({ data, notify }) {
  const save = useSave(data, notify);
  return (
    <>
      <header className="record-detail-heading recycle-list-heading">
        <div>
          <h1>Recycle Bin</h1>
          <p>Restore deleted records when you need them again.</p>
        </div>
        <span>{data.recycled.length} {data.recycled.length === 1 ? "record" : "records"}</span>
      </header>
      <ErrorMessage error={save.error} />
      {data.recycled.length === 0 ? (
        <section className="recycle-list-empty" aria-label="Empty Recycle Bin">
          <Icon name="trash" />
          <h2>No deleted records</h2>
          <p>Records moved to the Recycle Bin will appear here.</p>
          <Link to="/documents/index.php">Back to all records</Link>
        </section>
      ) : (
        <div className="record-table-wrap document-list-wrap" role="region" aria-label="Deleted document records" tabIndex={0}>
          <table className="record-table document-list-table recycle-list-table">
            <caption className="sr-only">Deleted documents with deletion dates and restore actions</caption>
            <colgroup><col className="list-code-column" /><col /><col className="recycle-date-column" /><col className="recycle-action-column" /></colgroup>
            <thead>
              <tr>
                <th scope="col">Document code</th>
                <th scope="col">Document title</th>
                <th scope="col">Deleted</th>
                <th scope="col">Action</th>
              </tr>
            </thead>
            <tbody>
              {data.recycled.map((row) => (
                <tr key={row.document_id}>
                  <td>{row.tracking_number}</td>
                  <td>
                    <Link className="record-title" reloadDocument to={`/documents/view.php?id=${row.document_id}`}>
                      {row.title}
                    </Link>
                  </td>
                  <td><time className="recycle-deleted-date" dateTime={row.deleted_at.replace(" ", "T")}><span>{row.deleted_at.slice(0, 10)}</span><span>{row.deleted_at.slice(11, 19)}</span></time></td>
                  <td>
                    <button
                      className="button button--secondary recycle-restore-button"
                      aria-label={`Restore ${row.tracking_number}`}
                      disabled={save.isPending}
                      onClick={() => {
                        const body = new FormData();
                        body.set("action", "restore");
                        body.set("document_id", row.document_id);
                        save.mutate({
                          path: "documents/recycle-bin.php",
                          body,
                        });
                      }}
                    >
                      {save.isPending && String(save.variables?.body.get("document_id")) === String(row.document_id) ? "Restoring…" : "Restore"}
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </>
  );
}

function Inventory({ data }) {
  const [params, setParams] = useSearchParams();
  const selected = params.get("location_id") || "";
  const folder = data.locations.find(
    (row) => String(row.location_id) === selected,
  );
  const path = folder ? storageCode(folder) : "";
  const docs = data.documents.filter((row) => String(row.location_id) === selected);
  return (
    <section className="inventory-page" aria-labelledby="inventory-page-title">
      <header className="inventory-page-heading">
        <h1 id="inventory-page-title">Folder inventory</h1>
        <p>Check recorded documents against the original papers in a folder.</p>
      </header>
      <div className="inventory-controls">
      <label className="modal-field react-inventory-select">
        <span>Original paper folder</span>
        <select
          value={selected}
          onChange={(event) => setParams(event.target.value ? { location_id: event.target.value } : {})}
          aria-controls="inventory-results"
        >
          <option value="">Choose folder</option>
          {data.types.map(type => (
            <optgroup key={type.type_id} label={type.type_name}>
              {data.locations.filter(row => String(row.type_id) === String(type.type_id)).map(row => (
                <option key={row.location_id} value={row.location_id}>{storageCode(row)}</option>
              ))}
            </optgroup>
          ))}
        </select>
      </label>
      <button className="button button--primary inventory-print-button" disabled={!folder} onClick={() => window.print()}>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" aria-hidden="true"><path d="M7 8V3h10v5M7 17H3V8h18v9h-4M7 14h10v7H7zM17 11h1" /></svg>
        Print inventory
      </button>
      </div>
      <div id="inventory-results" aria-live="polite">
      {!folder ? (
        <div className="inventory-empty-state">
          <Icon name="folder" />
          <h2>{selected ? "Folder not found" : "Choose a folder to get started"}</h2>
          <p>Select a storage location to view its recorded documents.</p>
        </div>
      ) : (
        <section className="react-inventory-results">
          <header className="inventory-result-heading">
            <div>
              <h2>{path}</h2>
              <p>Generated <time dateTime={data.today}>{data.today}</time></p>
            </div>
            <span role="status">{docs.length} {docs.length === 1 ? "record" : "records"}</span>
          </header>
          <p className="inventory-print-barangay">{data.profile?.barangay_name || "Barangay records"}</p>
          {docs.length ? <RecordTable documents={docs} /> : (
            <div className="inventory-empty-state"><h3>No records in this folder</h3><p>Choose another folder to continue checking the inventory.</p></div>
          )}
        </section>
      )}
      </div>
    </section>
  );
}

function Reports({ data }) {
  const [params, setParams] = useSearchParams();
  const selectedStatus = ["Pending", "Completed"].includes(params.get("status"))
    ? params.get("status")
    : "";
  const reportTypes = ["Pending", "Completed"].map((status) => ({
    status,
    count: data.documents.filter((row) => row.status === status).length,
  }));
  const rows = selectedStatus
    ? data.documents.filter((row) => row.status === selectedStatus)
    : [];
  const printDate = new Intl.DateTimeFormat("en-PH", {
    year: "numeric", month: "long", day: "numeric",
  }).format(new Date());

  return (
    <section className="report-page" aria-labelledby="report-page-title">
      <header className="report-page-heading">
        <div>
          <p className="report-eyebrow">PRINTABLE SUMMARIES</p>
          <h1 id="report-page-title">Document reports</h1>
          <p>Choose a processing report to preview or print.</p>
        </div>
        {selectedStatus && rows.length > 0 && <button className="button button--primary report-print-button" type="button" onClick={() => window.print()}>Print {selectedStatus.toLowerCase()} report</button>}
      </header>

      <p className="report-print-meta">
        {data.profile?.barangay_name || "Barangay records"} · {selectedStatus} documents · Generated {printDate}
      </p>

      <nav className="report-type-picker" aria-label="Choose a report">
        {reportTypes.map(({ status, count }) => (
          <Link
            className={`report-type-option${selectedStatus === status ? " is-active" : ""}`}
            key={status}
            to={`/reports/index.php?status=${encodeURIComponent(status)}`}
            aria-current={selectedStatus === status ? "page" : undefined}
          >
            <span className="report-type-option__copy">
              <strong>{status} documents</strong>
              <span>{status === "Pending" ? "Awaiting action" : "Finished processing"}</span>
            </span>
            <span className="report-type-option__count">{count}</span>
          </Link>
        ))}
      </nav>

      {!selectedStatus ? (
        <section className="report-empty report-prompt">
          <h2>Select a report</h2>
          <p>Choose Pending or Completed above to see its print-ready document list.</p>
        </section>
      ) : rows.length === 0 ? (
        <section className="report-empty" aria-live="polite">
          <h2>No {selectedStatus.toLowerCase()} documents</h2>
          <p>This report will include documents when their status is {selectedStatus.toLowerCase()}.</p>
        </section>
      ) : (
        <>
        <div className="report-selected-heading">
          <div><h2>{selectedStatus} documents</h2><p>{rows.length} {rows.length === 1 ? "document" : "documents"} · sorted by date recorded, newest first</p></div>
        </div>
        <div className="record-table-wrap report-table-wrap" role="region" aria-label={`${selectedStatus} document report`} tabIndex={0}>
          <table className="record-table report-table">
            <caption className="sr-only">Printable list of {selectedStatus.toLowerCase()} documents with category, received or released date, paper location, and date recorded</caption>
            <thead><tr>
              <th scope="col">Document ID</th>
              <th scope="col">Document title</th>
              <th scope="col">Category</th>
              <th scope="col">Received / released</th>
              <th scope="col">Paper location</th>
              <th scope="col">Date recorded</th>
            </tr></thead>
            <tbody>{rows.map((row) => (
              <tr key={row.id}>
                <td><strong>{row.tracking}</strong></td>
                <td><Link className="record-title" reloadDocument to={`/documents/view.php?id=${row.id}`}>{row.title}</Link></td>
                <td>{row.category}</td>
                <td>{row.recorded}</td>
                <td>{row.location}</td>
                <td>{String(row.recorded_at || "").slice(0, 10)}</td>
              </tr>
            ))}</tbody>
          </table>
        </div>
        </>
      )}
    </section>
  );
}

function Profile({ data, notify, onClose }) {
  const save = useSave(data, notify);
  return (
    <section className="sidebar-profile-editor" aria-labelledby="barangay-profile-title">
      <header className="sidebar-profile-editor__heading">
        <h2 id="barangay-profile-title">Barangay info</h2>
      </header>
      <form
        className="sidebar-profile-form"
        onSubmit={async (event) => {
          event.preventDefault();
          const body = new FormData(event.currentTarget);
          body.set("_action", "save_barangay_profile");
          try {
            await save.mutateAsync({ path: "dashboard.php", body });
            onClose();
          } catch {}
        }}
      >
        <ErrorMessage error={save.error} />
        <fieldset disabled={save.isPending}>
        {[
          ["barangay_name", "Barangay name", 150],
          ["location", "Location", 180],
          ["office_address", "Office address", 255],
          ["contact_number", "Contact number", 40],
        ].map(([name, label, maxLength]) => (
          <label className="sidebar-profile-field" key={name}>
            <span>{label} *</span>
            {name === "office_address" ? (
              <textarea name={name} defaultValue={data.profile?.[name] || ""} maxLength={maxLength} rows={2} required />
            ) : (
            <input
              name={name}
              type={name === "contact_number" ? "tel" : "text"}
              autoComplete={name === "contact_number" ? "tel" : "off"}
              placeholder={name === "location" ? "City / Municipality, Province" : undefined}
              defaultValue={data.profile?.[name] || ""}
              maxLength={maxLength}
              required
            />
            )}
          </label>
        ))}
        <div className="form-actions">
          <button
            type="button"
            className="button button--secondary"
            onClick={onClose}
          >
            Cancel
          </button>
          <button disabled={save.isPending} className="button button--primary">
            {save.isPending ? "Saving…" : "Save changes"}
          </button>
        </div>
        </fieldset>
      </form>
    </section>
  );
}

function App() {
  const query = useQuery({
    queryKey: ["workspace"],
    queryFn: () => request("api.php"),
  });
  const [open, setOpen] = useState(false);
  const [filters, setFilters] = useState(false);
  const [profile, setProfile] = useState(false);
  const [notice, setNotice] = useState("");
  const [params, setParams] = useSearchParams();
  const location = useLocation();
  const navigate = useNavigate();
  const searchInput = useRef(null);
  useEffect(() => {
    document.body.classList.toggle("sidebar-open", open);
    return () => document.body.classList.remove("sidebar-open");
  }, [open]);
  useEffect(() => {
    const workspace = document.querySelector(".react-workspace");
    const main = workspace?.querySelector(".main-content");
    if (!main || !CSS.supports("zoom", "1")) return;
    const observer = new ResizeObserver(([entry]) => {
      const width = workspace.getBoundingClientRect().width;
      if (width > 0)
        workspace.style.setProperty(
          "--workspace-content-scale",
          String(Math.min(1, entry.contentRect.width / width)),
        );
    });
    observer.observe(main);
    return () => observer.disconnect();
  }, [query.isPending]);
  useEffect(() => {
    if (params.has("profile")) {
      setProfile(true);
      setOpen(true);
    }
  }, [params]);
  useEffect(() => {
    if (!notice) return;
    const timer = setTimeout(() => setNotice(""), 4000);
    return () => clearTimeout(timer);
  }, [notice]);
  useEffect(() => {
    setOpen(false);
    document.title = "B-TRACK / Barangay records";
  }, [location.pathname]);
  useEffect(() => {
    const handleKey = (event) => {
      if (event.key === "Escape") setOpen(false);
    };
    window.addEventListener("keydown", handleKey);
    return () => window.removeEventListener("keydown", handleKey);
  }, []);
  if (query.isPending)
    return (
      <main className="page-transition-placeholder" aria-busy="true" aria-label="Opening workspace" />
    );
  if (query.error)
    return (
      <main className="react-loading">
        <ErrorMessage error={query.error} retry={query.refetch} />
      </main>
    );
  const data = query.data;
  const changeFilter = (name, value) => {
    const next = new URLSearchParams(params);
    next.delete("id");
    next.delete("from");
    value ? next.set(name, value) : next.delete(name);
    const destination =
      location.pathname === "/dashboard.php"
        ? "/dashboard.php"
        : "/documents/index.php";
    navigate(`${destination}?${next}`);
  };
  const pageNames = {
    "/dashboard.php": "Overview",
    "/documents/index.php": "All records",
    "/documents/view.php": "Record details",
    "/documents/edit.php": "Edit details",
    "/documents/recycle-bin.php": "Recycle Bin",
    "/documents/inventory.php": "Folder inventory",
    "/reports/index.php": "Reports",
  };
  const closeProfile = () => {
    setProfile(false);
    if (params.has("profile")) {
      params.delete("profile");
      setParams(params, { replace: true });
    }
  };
  const currentRecord = data.documents.find(
    (row) => String(row.id) === params.get("id"),
  );
  const activeFilterCount = Number(Boolean(params.get("year"))) + Number(Boolean(params.get("status")));
  const clearRecordFilters = () => {
    const next = new URLSearchParams(params);
    next.delete("year");
    next.delete("category");
    next.delete("status");
    next.delete("profile");
    next.delete("id");
    next.delete("from");
    const destination = location.pathname === "/dashboard.php" ? "/dashboard.php" : "/documents/index.php";
    navigate(`${destination}${next.size ? `?${next}` : ""}`);
  };
  const breadcrumbs = [{ label: "Overview", to: "/dashboard.php" }];
  if (currentRecord && params.get("from") === "list") {
    breadcrumbs.push({ label: "All records", to: "/documents/index.php" });
  }
  if (currentRecord && params.get("from") === "inventory") {
    breadcrumbs.push({ label: "Folder inventory", to: `/documents/inventory.php?location_id=${params.get("inventory_location") || ""}` });
  }
  if (currentRecord) breadcrumbs.push({ label: currentRecord.tracking, to: `/documents/view.php?${params}` });
  if (location.pathname !== "/dashboard.php") breadcrumbs.push({ label: pageNames[location.pathname] || "Records" });
  return (
    <>
      <a className="skip-link" href="#main-content">
        Skip to main content
      </a>
      <div className="app-shell records-workspace react-workspace">
        <aside
          className={`sidebar${profile ? " is-editing-profile" : ""}`}
          id="app-sidebar"
          aria-label="Primary navigation"
          aria-hidden={!open}
          inert={!open}
          onPointerLeave={(event) => {
            if (event.pointerType === "mouse") setOpen(false);
          }}
        >
          <div className="brand-lockup">
            <span className="brand-mark">BT</span>
            <div>
              <p className="brand-name">B-TRACK</p>
              <p className="brand-caption">Barangay records</p>
            </div>
          </div>
          <div className="sidebar-content">
            {profile && (
              <Profile data={data} notify={setNotice} onClose={closeProfile} />
            )}
            <form
              hidden={profile}
              className="filters sidebar-filters"
              role="search"
              onSubmit={(event) => {
                event.preventDefault();
                changeFilter("search", searchInput.current.value);
              }}
            >
              <div className="search-control">
                <button
                  type="submit"
                  className="search-submit"
                  aria-label="Search records"
                >
                  <Icon name="search" />
                </button>
                <label className="search-field">
                  <span className="sr-only">
                    Search by title or tracking number
                  </span>
                  <input
                    key={params.get("search")}
                    ref={searchInput}
                    type="search"
                    defaultValue={params.get("search") || ""}
                    placeholder="Search"
                  />
                </label>
                <button
                  className="filter-toggle"
                  type="button"
                  aria-label={`Search filters${activeFilterCount ? `, ${activeFilterCount} active` : ""}`}
                  aria-controls="record-filter-panel"
                  aria-expanded={filters}
                  onClick={() => setFilters(!filters)}
                >
                  <Icon name="filter" />
                  {activeFilterCount > 0 && <span className="filter-count" aria-hidden="true">{activeFilterCount}</span>}
                </button>
              </div>
              {filters && (
                <div className="react-filters" id="record-filter-panel" role="group" aria-label="Record filters">
                  <div className="react-filters-heading">
                    <strong>Filters</strong>
                    <button type="button" onClick={clearRecordFilters} disabled={!activeFilterCount}>Clear</button>
                  </div>
                  <label>
                    <span>Year</span>
                    <select
                      value={params.get("year") || ""}
                      onChange={(event) =>
                        changeFilter("year", event.target.value)
                      }
                    >
                      <option value="">All years</option>
                      {[
                        ...new Set(
                          data.documents.map((row) => row.recorded.slice(0, 4)),
                        ),
                      ]
                        .sort()
                        .reverse()
                        .map((year) => (
                          <option key={year}>{year}</option>
                        ))}
                    </select>
                  </label>
                  <label>
                    <span>Status</span>
                    <select
                      value={params.get("status") || ""}
                      onChange={(event) =>
                        changeFilter("status", event.target.value)
                      }
                    >
                      <option value="">All statuses</option>
                      {["Pending", "In Process", "Completed"].map((status) => (
                        <option key={status}>{status}</option>
                      ))}
                    </select>
                  </label>
                </div>
              )}
            </form>
            <p className="sidebar-menu-label" hidden={profile}>
              Menu
            </p>
            <nav className="primary-nav" hidden={profile}>
              <Link className="nav-link" to="/documents/index.php">
                <Icon name="record" />
                All records
              </Link>
              <Link className="nav-link" to="/documents/inventory.php">
                <Icon name="folder" />
                Folder inventory
              </Link>
              <Link className="nav-link" to="/reports/index.php">
                <Icon name="reports" />
                Reports
              </Link>
              <button
                className="nav-link"
                onClick={() => {
                  setProfile(true);
                }}
              >
                <Icon name="building" />
                Barangay info
              </button>
              <Link className="nav-link" to="/documents/recycle-bin.php">
                <Icon name="trash" />
                Recycle Bin
              </Link>
            </nav>
          </div>
          <footer className="sidebar-footer">
            <div className="operator-panel">
              <span>SECRETARY</span>
              <strong>{data.user.full_name}</strong>
            </div>
            <form className="sign-out" method="post" action={url("logout.php")}>
              <input type="hidden" name="_token" value={data.csrf} />
              <button>
                <span>Sign out</span>
                <Icon name="exit" />
              </button>
            </form>
          </footer>
        </aside>
        {open && (
          <button
            className="sidebar-backdrop react-backdrop"
            aria-label="Close menu"
            onClick={() => setOpen(false)}
          />
        )}
        <main className="main-content" id="main-content">
          <div className="records-content">
            <header className="records-header">
              <button
                className="menu-toggle"
                aria-label="Open menu"
                aria-expanded={open}
                aria-controls="app-sidebar"
                onClick={() => {
                  setOpen(true);
                  requestAnimationFrame(() =>
                    document.querySelector(".primary-nav a")?.focus(),
                  );
                }}
              >
                <Icon name="menu" />
              </button>
              <nav className="page-breadcrumb" aria-label="Breadcrumb">
                <svg className="page-breadcrumb__mark" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 3h10l5 5v13H5z" /><path d="M15 3v6h5M8 13h8M8 17h8" /></svg>
                <ol className="page-breadcrumb__trail" role="list">
                  {breadcrumbs.map((item, index) => (
                    <li key={`${item.label}-${index}`}>
                      <span aria-hidden="true">›</span>
                      {index === breadcrumbs.length - 1
                        ? <strong aria-current="page">{item.label}</strong>
                        : <Link to={item.to} reloadDocument={item.to?.startsWith("/documents/view.php")}>{item.label}</Link>}
                    </li>
                  ))}
                </ol>
              </nav>
            </header>
            <div className="react-page" key={location.pathname}>
              <Routes>
                <Route
                  path="/dashboard.php"
                  element={<Records data={data} notify={setNotice} />}
                />
                <Route
                  path="/documents/index.php"
                  element={<Records data={data} list notify={setNotice} />}
                />
                <Route
                  path="/documents/view.php"
                  element={<OriginalRecordPage />}
                />
                <Route
                  path="/documents/edit.php"
                  element={<Record data={data} notify={setNotice} editing />}
                />
                <Route
                  path="/documents/recycle-bin.php"
                  element={<RecycleBin data={data} notify={setNotice} />}
                />
                <Route
                  path="/documents/inventory.php"
                  element={<Inventory data={data} />}
                />
                <Route
                  path="/reports/index.php"
                  element={<Reports data={data} />}
                />
                <Route
                  path="*"
                  element={
                    <p>
                      Page not found.{" "}
                      <Link to="/dashboard.php">Return to dashboard</Link>
                    </p>
                  }
                />
              </Routes>
            </div>
          </div>
        </main>
      </div>
      {notice && (
        <div className="react-toast" role="status">
          {notice}
        </div>
      )}
    </>
  );
}

function Auth() {
  const location = useLocation();
  const navigate = useNavigate();
  const registering = location.pathname.endsWith("/register.php");
  const token = useQuery({
    queryKey: ["auth-token"],
    queryFn: () => request("api.php?auth=1"),
  });
  const [visible, setVisible] = useState(false);
  const [confirmVisible, setConfirmVisible] = useState(false);
  const [attempted, setAttempted] = useState(false);
  const [notice, setNotice] = useState("");
  const save = useMutation({
    mutationFn: async (body) => {
      body.set("_token", token.data.csrf);
      const result = await request(registering ? "register.php" : "login.php", {
        method: "POST",
        body,
        headers: { "X-BTRACK-React": "1" },
      });
      if (!result.ok)
        throw new Error(result.message || "Check your details and try again.");
      return result;
    },
  });
  const submit = async (event) => {
    event.preventDefault();
    try {
      const result = await save.mutateAsync(new FormData(event.currentTarget));
      if (registering) {
        setNotice(
          result.notice?.message || "Account created. Sign in to continue.",
        );
        save.reset();
        navigate("/login.php");
      } else {
        window.location.assign(url("dashboard.php"));
      }
    } catch {
      /* Keep the entered details available for correction. */
    }
  };
  return (
    <main className="login-shell">
      <section className="login-card auth-card" aria-labelledby="auth-title">
        <header className="system-header">
          <div className="brand-lockup">
            <span className="brand-mark">BT</span>
            <span className="brand-name">B-TRACK</span>
          </div>
        </header>
        <div className="login-content">
          <div className="intro">
            <h1 id="auth-title">{registering ? "Create account" : "Sign in to continue"}</h1>
          </div>
          {notice && <p className="notice notice--success" role="status">{notice}</p>}
          <ErrorMessage error={token.error || save.error} />
          <form
            className={`login-form${attempted ? " auth-submitted" : ""}`}
            id={registering ? "register-form" : "login-form"}
            key={location.pathname}
            onSubmit={submit}
            onInvalid={() => setAttempted(true)}
          >
            <fieldset disabled={!token.data || save.isPending}>
              {registering && (
                <label className="field">
                  <span>Full name <span className="auth-required">*</span></span>
                  <input
                    name="full_name"
                    autoComplete="name"
                    required
                    maxLength={100}
                  />
                </label>
              )}
              <label className="field">
                <span>Username <span className="auth-required">*</span></span>
                <input
                  id="username"
                  name="username"
                  autoComplete="username"
                  required
                  maxLength={50}
                />
              </label>
              <div className="field">
                <label htmlFor="password">Password <span className="auth-required">*</span></label>
                <div className="react-password">
                  <input
                    id="password"
                    name="password"
                    type={visible ? "text" : "password"}
                    autoComplete={
                      registering ? "new-password" : "current-password"
                    }
                    required
                    minLength={registering ? 8 : undefined}
                    aria-describedby={registering ? "password-hint" : undefined}
                  />
                  <button
                    type="button"
                    aria-label={visible ? "Hide password" : "Show password"}
                    aria-pressed={visible}
                    onClick={() => setVisible(!visible)}
                  >
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                      <path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z" />
                      <circle cx="12" cy="12" r="3" />
                    </svg>
                  </button>
                </div>
                {registering && <small id="password-hint">At least 8 characters.</small>}
              </div>
              {registering && (
                <div className="field">
                  <label htmlFor="confirm-password">Confirm password <span className="auth-required">*</span></label>
                  <div className="react-password">
                  <input
                    id="confirm-password"
                    name="confirm_password"
                    type={confirmVisible ? "text" : "password"}
                    autoComplete="new-password"
                    required
                  />
                  <button type="button" aria-label={confirmVisible ? "Hide confirmed password" : "Show confirmed password"} aria-pressed={confirmVisible} onClick={() => setConfirmVisible(!confirmVisible)}>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z" /><circle cx="12" cy="12" r="3" /></svg>
                  </button>
                  </div>
                </div>
              )}
              <p className="react-auth-link">
                {registering ? "Already registered? " : "New to B-TRACK? "}
                <Link
                  to={registering ? "/login.php" : "/register.php"}
                  onClick={() => {
                    save.reset();
                    setNotice("");
                    setVisible(false);
                    setConfirmVisible(false);
                    setAttempted(false);
                  }}
                >
                  {registering ? "Sign in" : "Create account"}
                </Link>
              </p>
              <button className="submit-button" type="submit">
                {save.isPending
                  ? "Please wait…"
                  : registering
                    ? "Create account"
                    : "Enter system"}
              </button>
            </fieldset>
          </form>
        </div>
      </section>
    </main>
  );
}

const authenticationPage = /\/(login|register)\.php$/.test(
  window.location.pathname,
);

createRoot(document.getElementById("btrack-app")).render(
  <QueryClientProvider client={client}>
    <BrowserRouter basename={base.replace(/\/$/, "") || "/"}>
      {authenticationPage ? <Auth /> : <App />}
    </BrowserRouter>
  </QueryClientProvider>,
);
