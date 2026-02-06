from fastapi import HTTPException


def check_permission(user_role: str, autonomy_level: str):
    if autonomy_level == 'safe':
        return
    if autonomy_level == 'assisted' and user_role in {'user', 'admin'}:
        return
    if autonomy_level == 'admin' and user_role == 'admin':
        return
    raise HTTPException(status_code=403, detail='Permission denied for this avatar autonomy level')
