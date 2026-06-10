import React from "react";
import { X } from "lucide-react";
const Modal = ({ id, children, title }) => {
  return (
    <dialog id={id} className="modal">
      <div className="modal-box bg-base-100 text-base-content relative space-y-4 ">
        <div className="flex items-center justify-between mb-2">
          <h3 className="text-lg font-bold">{title}</h3>
          <button
            onClick={() => document.getElementById(id).close()}
            className="btn btn-xs w-4 h-4 btn-circle btn-ghost bg-error border border-error text-white hover:bg-transparent hover:text-error absolute left-3 top-3"
          >
            <X className="h-3 w-3" />
          </button>
        </div>
        {children}
      </div>
      <form method="dialog" className="modal-backdrop">
        <button>close</button>
      </form>
    </dialog>
  );
};

export default Modal;
