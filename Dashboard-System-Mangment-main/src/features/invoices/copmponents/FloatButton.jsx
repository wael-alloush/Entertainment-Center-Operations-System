import React from "react";
import { Plus } from "lucide-react";

const FloatButton = ({ className, onClick }) => {
  return (
    <div
      dir="ltr"
      className={`fab absolute bottom-6 ${className} z-99 transition-all duration-300`}
    >
      <button
        onClick={onClick}
        role="button"
        className="btn btn-xl btn-circle  border-2 hover:bg-transparent bg-primary border-primary rounded-full hover:text-primary text-primary-content transition-all duration-300"
      >
        <Plus className="w-8 h-8" />
      </button>
    </div>
  );
};

export default FloatButton;
