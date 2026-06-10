import React from "react";

const MyButton = ({ onClick, title, children, className, ...props }) => {
  return (
    <button
      {...props}
      // title={title}
      onClick={onClick}
      className={`btn btn-sm md:btn-md  rounded-lg ${className}`}
    >
      {children}

      {title}
    </button>
  );
};

export default MyButton;
